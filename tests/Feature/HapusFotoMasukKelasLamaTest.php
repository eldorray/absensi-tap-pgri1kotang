<?php

use App\Models\AbsensiKelas;
use App\Models\TahunAjaran;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // 60 hari sebelum 2026-11-06 adalah 2026-09-07.
    Carbon::setTestNow('2026-11-06 01:00:00');
    Storage::fake('local');
    $this->seed(JadwalKerjaSeeder::class);
});

function absenBerfoto(string $tanggal, array $atribut = []): AbsensiKelas
{
    $path = 'absensi-kelas/'.str_replace('-', '/', substr($tanggal, 0, 7)).'/'.$tanggal.'.jpg';
    Storage::disk('local')->put($path, 'foto');

    return AbsensiKelas::factory()->create([...$atribut, 'tanggal' => $tanggal, 'foto_path' => $path]);
}

test('foto lebih tua dari 60 hari dihapus, barisnya tetap', function () {
    $lama = absenBerfoto('2026-09-06');
    $baru = absenBerfoto('2026-09-07');

    $this->artisan('masuk-kelas:hapus-foto-lama')->assertSuccessful();

    expect($lama->fresh()->foto_path)->toBeNull()
        ->and($baru->fresh()->foto_path)->not->toBeNull();
    Storage::disk('local')->assertMissing('absensi-kelas/2026/09/2026-09-06.jpg');
    Storage::disk('local')->assertExists('absensi-kelas/2026/09/2026-09-07.jpg');
});

test('foto tahun ajaran lalu ikut dihapus', function () {
    $lalu = TahunAjaran::factory()->create(['nama' => '2025/2026', 'tanggal_mulai' => '2025-07-01', 'tanggal_selesai' => '2026-06-30']);
    $absen = absenBerfoto('2026-05-04', ['tahun_ajaran_id' => $lalu->id]);

    $this->artisan('masuk-kelas:hapus-foto-lama')->assertSuccessful();

    expect(AbsensiKelas::withoutGlobalScopes()->find($absen->id)?->foto_path)->toBeNull();
    Storage::disk('local')->assertMissing('absensi-kelas/2026/05/2026-05-04.jpg');
});

test('scheduler menjalankan retensi foto', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('masuk-kelas:hapus-foto-lama')
        ->assertSuccessful();
});

test('file foto yatim yang lebih tua dari 60 hari ikut dihapus', function () {
    // Baris yang hilang lewat cascade (guru atau kelas dihapus) meninggalkan
    // file tanpa baris; retensi tetap harus menyapunya.
    $disk = Storage::disk('local');
    $disk->put('absensi-kelas/2026/08/yatim.jpg', 'foto');
    touch($disk->path('absensi-kelas/2026/08/yatim.jpg'), now()->subDays(61)->getTimestamp());
    $disk->put('absensi-kelas/2026/10/baru.jpg', 'foto');
    touch($disk->path('absensi-kelas/2026/10/baru.jpg'), now()->subDays(10)->getTimestamp());

    $this->artisan('masuk-kelas:hapus-foto-lama')->assertSuccessful();

    $disk->assertMissing('absensi-kelas/2026/08/yatim.jpg');
    $disk->assertExists('absensi-kelas/2026/10/baru.jpg');
});
