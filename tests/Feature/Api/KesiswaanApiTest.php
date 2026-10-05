<?php

use App\Enums\StatusSesiAbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\IzinOrangTua;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    tahunAjaranAktif('2026/2027');
    Carbon::setTestNow('2026-09-14 08:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{0: Kelas, 1: Siswa}
 */
function kelasApi(User $wali): array
{
    $kantor = Kantor::factory()->create();
    $kelas = Kelas::factory()->create(['kantor_id' => $kantor->id, 'wali_kelas_id' => $wali->id, 'nama' => '8B']);
    $siswa = Siswa::factory()->create(['kantor_id' => $kantor->id]);
    AnggotaKelas::factory()->create(['kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'tanggal_mulai' => '2026-07-01']);

    return [$kelas, $siswa];
}

test('wali kelas membaca lembar absensi siswa tetapi tidak bisa mengisinya', function () {
    $wali = User::factory()->create();
    [$kelas, $siswa] = kelasApi($wali);
    Sanctum::actingAs($wali);

    $this->getJson(route('api.absensi-siswa.index'))
        ->assertOk()
        ->assertJsonPath('piket', false)
        ->assertJsonPath('kelas.0.nama', '8B');

    $this->getJson(route('api.absensi-siswa.show', $kelas))
        ->assertOk()
        ->assertJsonPath('siswa.0.id', $siswa->id)
        ->assertJsonPath('sesi.dapat_mengisi', false);

    $this->putJson(route('api.absensi-siswa.draft', $kelas), [
        'tanggal' => '2026-09-14',
        'absensis' => [['siswa_id' => $siswa->id, 'status' => 'sakit']],
    ])->assertForbidden();
});

test('guru yang tidak mengampu kelas tidak bisa membaca lembarnya', function () {
    [$kelas] = kelasApi(User::factory()->create());
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.absensi-siswa.show', $kelas))->assertForbidden();
});

test('guru piket mengisi lalu memfinalisasi absensi siswa lewat API', function () {
    [$kelas, $siswa] = kelasApi(User::factory()->create());
    Sanctum::actingAs(User::factory()->admin()->create());

    $this->putJson(route('api.absensi-siswa.draft', $kelas), [
        'tanggal' => '2026-09-14',
        'absensis' => [['siswa_id' => $siswa->id, 'status' => 'sakit']],
    ])
        ->assertOk()
        ->assertJsonPath('sesi.status', StatusSesiAbsensiSiswa::Draft->value)
        ->assertJsonPath('siswa.0.status', 'sakit');

    $this->putJson(route('api.absensi-siswa.finalisasi', $kelas), [
        'tanggal' => '2026-09-14',
        'absensis' => [['siswa_id' => $siswa->id, 'status' => 'sakit']],
    ])
        ->assertOk()
        ->assertJsonPath('sesi.status', StatusSesiAbsensiSiswa::Final->value)
        ->assertJsonPath('sesi.dapat_mengisi', false);
});

test('orang tua hanya melihat anaknya sendiri walau meminta id anak lain', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create(['nama' => 'Aisyah']);
    $anakOrangLain = Siswa::factory()->create(['nama' => 'Bukan Anaknya']);
    $orangTua->siswas()->attach($anak);
    Sanctum::actingAs($orangTua);

    $this->getJson(route('api.orang-tua.kehadiran', ['siswa' => $anakOrangLain->id]))
        ->assertOk()
        ->assertJsonCount(1, 'anak')
        ->assertJsonPath('siswaTerpilih.id', $anak->id);
});

test('orang tua mengajukan izin anak lewat API, tetapi tidak untuk anak orang lain', function () {
    $orangTua = User::factory()->orangTua()->create();
    $anak = Siswa::factory()->create();
    $orangTua->siswas()->attach($anak);
    Sanctum::actingAs($orangTua);

    $payload = [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-15',
        'tanggal_selesai' => '2026-09-16',
        'alasan' => 'Demam dan perlu istirahat.',
    ];

    $this->postJson(route('api.orang-tua.izin.store'), ['siswa_id' => Siswa::factory()->create()->id, ...$payload])
        ->assertForbidden();

    $this->postJson(route('api.orang-tua.izin.store'), ['siswa_id' => $anak->id, ...$payload])
        ->assertCreated();

    $this->getJson(route('api.orang-tua.izin.index'))
        ->assertOk()
        ->assertJsonPath('izins.0.status', 'pending')
        ->assertJsonPath('anak.0.id', $anak->id);

    expect(IzinOrangTua::count())->toBe(1);
});

test('wilayah orang tua tertutup bagi guru', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson(route('api.orang-tua.kehadiran'))->assertForbidden();
});
