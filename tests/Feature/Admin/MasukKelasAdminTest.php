<?php

use App\Models\AbsensiKelas;
use App\Models\JadwalKerja;
use App\Models\Kelas;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 06:55:00'); // Senin
    Storage::fake('local');
    $this->seed(JadwalKerjaSeeder::class);
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => '06:50:00']);
});

test('admin membuka foto masuk kelas', function () {
    Storage::disk('local')->put('absensi-kelas/2026/09/a.jpg', 'isi-foto');
    $absen = AbsensiKelas::factory()->create(['foto_path' => 'absensi-kelas/2026/09/a.jpg']);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.masuk-kelas.foto', $absen))
        ->assertOk();

    expect($response->streamedContent())->toBe('isi-foto');
});

test('guru dan orang tua tidak bisa membuka foto masuk kelas', function () {
    Storage::disk('local')->put('absensi-kelas/2026/09/a.jpg', 'isi-foto');
    $absen = AbsensiKelas::factory()->create(['foto_path' => 'absensi-kelas/2026/09/a.jpg']);

    $this->actingAs($absen->user)->get(route('admin.masuk-kelas.foto', $absen))->assertForbidden();
    $this->actingAs(User::factory()->orangTua()->create())->get(route('admin.masuk-kelas.foto', $absen))->assertForbidden();
});

test('foto yang sudah dihapus retensi menjawab 404', function () {
    $absen = AbsensiKelas::factory()->create(['foto_path' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.masuk-kelas.foto', $absen))
        ->assertNotFound();
});

test('dashboard admin memuat pantauan masuk kelas', function () {
    Kelas::factory()->create(['nama' => '7A', 'tingkat' => 7]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p
            ->where('masukKelas.batas', '06:50')
            ->where('masukKelas.lewat', true)
            ->where('masukKelas.kelas.0.status', 'kosong'));
});
