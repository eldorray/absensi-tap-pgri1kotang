<?php

use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\AbsensiKelas;
use App\Models\Izin;
use App\Models\JadwalKerja;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Perangkat;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 06:45:00');
    Storage::fake('local');
});

test('riwayat API memakai ringkasan yang sama dengan halaman web', function () {
    [$guru] = guruSiapAbsen();
    Sanctum::actingAs($guru);

    $this->getJson(route('api.riwayat', ['bulan' => '2026-09']))
        ->assertOk()
        ->assertJsonPath('bulan.nilai', '2026-09')
        ->assertJsonStructure(['ringkasan' => ['hari_efektif', 'hadir', 'terlambat', 'alfa', 'persentase'], 'hari']);

    $this->getJson(route('api.riwayat', ['bulan' => '2026-10']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bulan');
});

test('riwayat API memuat jam tap hari ini', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    Carbon::setTestNow('2026-09-07 07:00:00');
    Sanctum::actingAs($guru);

    $this->postJson(route('api.absensi.store'), [
        'tipe' => 'masuk',
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
    ])->assertOk();

    $this->getJson(route('api.riwayat'))
        ->assertOk()
        ->assertJsonPath('hari.0.tanggal', '2026-09-07')
        ->assertJsonPath('hari.0.jam_masuk', '07:00');
});

test('guru mengajukan izin berlampiran lewat API dan melihatnya di daftar', function () {
    $guru = User::factory()->create();
    Sanctum::actingAs($guru);

    $this->postJson(route('api.izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-08',
        'tanggal_selesai' => '2026-09-09',
        'alasan' => 'Demam, disarankan dokter istirahat.',
        'lampiran' => UploadedFile::fake()->create('surat-dokter.pdf', 200, 'application/pdf'),
    ])->assertCreated();

    $izin = Izin::sole();
    Storage::disk('local')->assertExists($izin->lampiran_path);

    $this->getJson(route('api.izin.index'))
        ->assertOk()
        ->assertJsonPath('izins.0.tipe', 'sakit')
        ->assertJsonPath('izins.0.status', 'pending')
        ->assertJsonPath('izins.0.ada_lampiran', true);
});

test('izin API tetap memakai validasi web', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route('api.izin.store'), [
        'tipe' => 'sakit',
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2026-09-01',
        'alasan' => 'Lalu',
    ])->assertUnprocessable()->assertJsonValidationErrors(['tanggal_mulai', 'alasan']);

    expect(Izin::count())->toBe(0);
});

test('masuk kelas lewat API menyimpan foto dan menolak HP asing', function () {
    $this->seed(JadwalKerjaSeeder::class);
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => '06:50:00']);
    $kantor = Kantor::factory()->create();
    $guru = User::factory()->create(['kantor_id' => $kantor->id]);
    $perangkat = Perangkat::factory()->for($guru)->create();
    $attempt = AbsensiAttempt::factory()->create(['user_id' => $guru->id, 'perangkat_uuid' => $perangkat->uuid]);
    Absensi::factory()->create(['user_id' => $guru->id, 'masuk_attempt_id' => $attempt->id]);
    $kelas = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '7A', 'tingkat' => 7]);
    Sanctum::actingAs($guru);

    $this->postJson(route('api.masuk-kelas.store'), [
        'kelas_id' => $kelas->id,
        'foto' => UploadedFile::fake()->create('kelas.jpg', 150, 'image/jpeg'),
        'device_uuid' => Perangkat::factory()->create()->uuid,
    ])->assertUnprocessable()->assertJsonValidationErrors('masuk_kelas');

    $this->postJson(route('api.masuk-kelas.store'), [
        'kelas_id' => $kelas->id,
        'foto' => UploadedFile::fake()->create('kelas.jpg', 150, 'image/jpeg'),
        'device_uuid' => $perangkat->uuid,
    ])
        ->assertCreated()
        ->assertJsonPath('absen.kelas', '7A')
        ->assertJsonPath('absen.menitTerlambat', 0);

    Storage::disk('local')->assertExists(AbsensiKelas::sole()->foto_path);
});
