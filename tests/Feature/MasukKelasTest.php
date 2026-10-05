<?php

use App\Models\Absensi;
use App\Models\AbsensiAttempt;
use App\Models\AbsensiKelas;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\Perangkat;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    // 2026-09-07 Senin. Batas masuk kelas 06.50 setiap hari.
    Carbon::setTestNow('2026-09-07 06:45:00');
    Storage::fake('local');
    $this->seed(JadwalKerjaSeeder::class);
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => '06:50:00']);
});

/**
 * Guru yang sudah tap masuk hari ini, dengan HP aktif dan kelas 7A di unitnya.
 *
 * @return array{0: User, 1: Perangkat, 2: Kelas}
 */
function guruSudahTapMasuk(): array
{
    $kantor = Kantor::factory()->create();
    $guru = User::factory()->create(['kantor_id' => $kantor->id]);
    $perangkat = Perangkat::factory()->for($guru)->create();
    $attempt = AbsensiAttempt::factory()->create(['user_id' => $guru->id, 'perangkat_uuid' => $perangkat->uuid]);
    Absensi::factory()->create(['user_id' => $guru->id, 'masuk_attempt_id' => $attempt->id]);

    return [$guru, $perangkat, Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '7A', 'tingkat' => 7])];
}

/**
 * Foto palsu ber-MIME JPEG; tidak butuh ekstensi GD.
 *
 * @return array<string, mixed>
 */
function payloadMasukKelas(Perangkat $perangkat, Kelas $kelas): array
{
    return [
        'kelas_id' => $kelas->id,
        'foto' => UploadedFile::fake()->create('masuk-kelas.jpg', 150, 'image/jpeg'),
        'device_uuid' => $perangkat->uuid,
    ];
}

test('guru masuk kelas sebelum batas tercatat tepat waktu dengan fotonya', function () {
    [$guru, $perangkat, $kelas] = guruSudahTapMasuk();

    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    $absen = AbsensiKelas::sole();

    expect($absen->user_id)->toBe($guru->id)
        ->and($absen->kelas_id)->toBe($kelas->id)
        ->and($absen->menit_terlambat)->toBe(0)
        ->and($absen->perangkat_uuid)->toBe($perangkat->uuid)
        ->and($absen->foto_path)->toStartWith('absensi-kelas/2026/09/');
    Storage::disk('local')->assertExists($absen->foto_path);
});

test('masuk kelas lewat batas tercatat telat dalam menit penuh', function () {
    Carbon::setTestNow('2026-09-07 06:57:30');
    [$guru, $perangkat, $kelas] = guruSudahTapMasuk();

    $this->actingAs($guru)->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas));

    expect(AbsensiKelas::sole()->menit_terlambat)->toBe(7);
});

test('detik ke-59 setelah batas masih tepat waktu', function () {
    Carbon::setTestNow('2026-09-07 06:50:59');
    [$guru, $perangkat, $kelas] = guruSudahTapMasuk();

    $this->actingAs($guru)->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas));

    expect(AbsensiKelas::sole()->menit_terlambat)->toBe(0);
});

test('guru yang belum tap masuk ditolak tanpa menyimpan foto', function () {
    $kelas = Kelas::factory()->create();
    $guru = User::factory()->create(['kantor_id' => $kelas->kantor_id]);
    $perangkat = Perangkat::factory()->for($guru)->create();

    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas))
        ->assertSessionHasErrors(['masuk_kelas' => 'Tap masuk dulu sebelum absen masuk kelas.']);

    expect(AbsensiKelas::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('HP yang tidak terdaftar ditolak', function () {
    [$guru, , $kelas] = guruSudahTapMasuk();
    $asing = Perangkat::factory()->make(['uuid' => (string) Str::uuid()]);

    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), payloadMasukKelas($asing, $kelas))
        ->assertSessionHasErrors('masuk_kelas');

    expect(AbsensiKelas::count())->toBe(0);
});

test('absen kelas kedua di hari yang sama ditolak', function () {
    [$guru, $perangkat, $kelas] = guruSudahTapMasuk();

    $this->actingAs($guru)->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas));
    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas))
        ->assertSessionHasErrors(['masuk_kelas' => 'Sudah absen masuk kelas hari ini.']);

    expect(AbsensiKelas::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('hari tanpa jam masuk kelas ditolak', function () {
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => null]);
    [$guru, $perangkat, $kelas] = guruSudahTapMasuk();

    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas))
        ->assertSessionHasErrors(['masuk_kelas' => 'Hari ini tidak ada absen masuk kelas.']);
});

test('hari libur ditolak', function () {
    HariLibur::factory()->create(['tanggal' => '2026-09-07']);
    [$guru, $perangkat, $kelas] = guruSudahTapMasuk();

    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas))
        ->assertSessionHasErrors('masuk_kelas');
});

test('kelas di unit lain dan kelas nonaktif ditolak', function () {
    [$guru, $perangkat] = guruSudahTapMasuk();
    $unitLain = Kelas::factory()->create();
    $nonaktif = Kelas::factory()->create(['kantor_id' => $guru->kantor_id, 'is_active' => false]);

    foreach ([$unitLain, $nonaktif] as $kelas) {
        $this->actingAs($guru)
            ->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, $kelas))
            ->assertSessionHasErrors(['masuk_kelas' => 'Kelas tidak ditemukan.']);
    }

    expect(AbsensiKelas::count())->toBe(0);
});

test('guru tanpa unit boleh memilih kelas unit mana pun', function () {
    [$guru, $perangkat] = guruSudahTapMasuk();
    $guru->forceFill(['kantor_id' => null])->save();

    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), payloadMasukKelas($perangkat, Kelas::factory()->create()))
        ->assertSessionHasNoErrors();

    expect(AbsensiKelas::count())->toBe(1);
});

test('foto harus JPEG dan paling besar 1 MB', function () {
    [$guru, $perangkat, $kelas] = guruSudahTapMasuk();

    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), [...payloadMasukKelas($perangkat, $kelas), 'foto' => UploadedFile::fake()->create('kelas.png', 100, 'image/png')])
        ->assertSessionHasErrors('foto');
    $this->actingAs($guru)
        ->post(route('masuk-kelas.store'), [...payloadMasukKelas($perangkat, $kelas), 'foto' => UploadedFile::fake()->create('kelas.jpg', 1500, 'image/jpeg')])
        ->assertSessionHasErrors('foto');

    expect(AbsensiKelas::count())->toBe(0);
});

test('orang tua tidak bisa absen masuk kelas', function () {
    $this->actingAs(User::factory()->orangTua()->create())
        ->post(route('masuk-kelas.store'), [])
        ->assertForbidden();
});
