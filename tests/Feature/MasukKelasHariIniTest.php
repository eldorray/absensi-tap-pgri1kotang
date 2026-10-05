<?php

use App\Actions\Absensi\MasukKelasHariIni;
use App\Models\AbsensiKelas;
use App\Models\JadwalKerja;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 06:55:00'); // Senin
    $this->seed(JadwalKerjaSeeder::class);
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => '06:50:00']);
    $this->kantor = Kantor::factory()->create(['nama' => 'SMP']);
});

function kelasDi(Kantor $kantor, string $nama, int $tingkat): Kelas
{
    return Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => $nama, 'tingkat' => $tingkat]);
}

test('hari tanpa jam masuk kelas tidak punya status', function () {
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => null]);

    expect(app(MasukKelasHariIni::class)())->toBeNull();
});

test('status tiap kelas: tepat, telat, dan kosong setelah batas', function () {
    $tepat = kelasDi($this->kantor, '7A', 7);
    $telat = kelasDi($this->kantor, '8A', 8);
    kelasDi($this->kantor, '9A', 9);
    AbsensiKelas::factory()->create(['kelas_id' => $tepat->id, 'user_id' => User::factory()->create(['name' => 'Bu Ani'])->id]);
    AbsensiKelas::factory()->telat(3)->create(['kelas_id' => $telat->id]);

    $hasil = app(MasukKelasHariIni::class)();

    expect($hasil['batas'])->toBe('06:50')
        ->and($hasil['lewat'])->toBeTrue()
        ->and(array_column($hasil['kelas'], 'status', 'nama'))->toBe(['7A' => 'tepat', '8A' => 'telat', '9A' => 'kosong'])
        ->and($hasil['kelas'][0]['unit'])->toBe('SMP')
        ->and($hasil['kelas'][0]['guru'][0]['nama'])->toBe('Bu Ani')
        ->and($hasil['kelas'][0]['guru'][0]['adaFoto'])->toBeTrue();
});

test('sebelum menit pertama lewat, kelas tanpa guru masih menunggu', function () {
    Carbon::setTestNow('2026-09-07 06:50:59');
    kelasDi($this->kantor, '7A', 7);

    $hasil = app(MasukKelasHariIni::class)();

    expect($hasil['lewat'])->toBeFalse()
        ->and($hasil['kelas'][0]['status'])->toBe('menunggu');
});

test('absen pertama yang menentukan status walau guru kedua telat', function () {
    $kelas = kelasDi($this->kantor, '7A', 7);
    Carbon::setTestNow('2026-09-07 06:45:00');
    AbsensiKelas::factory()->create(['kelas_id' => $kelas->id]);
    Carbon::setTestNow('2026-09-07 06:58:00');
    AbsensiKelas::factory()->telat(8)->create(['kelas_id' => $kelas->id]);

    $hasil = app(MasukKelasHariIni::class)();

    expect($hasil['kelas'][0]['status'])->toBe('tepat')
        ->and($hasil['kelas'][0]['guru'])->toHaveCount(2)
        ->and($hasil['kelas'][0]['guru'][1]['menitTerlambat'])->toBe(8);
});

test('jam batas yang diubah belakangan tidak mengubah status yang sudah tercatat', function () {
    $kelas = kelasDi($this->kantor, '7A', 7);
    AbsensiKelas::factory()->create(['kelas_id' => $kelas->id, 'menit_terlambat' => 0]);
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => '06:30:00']);

    expect(app(MasukKelasHariIni::class)()['kelas'][0]['status'])->toBe('tepat');
});

test('filter unit hanya memuat kelas aktif di unit itu', function () {
    kelasDi($this->kantor, '7A', 7);
    Kelas::factory()->create(['kantor_id' => $this->kantor->id, 'nama' => '7B', 'tingkat' => 7, 'is_active' => false]);
    Kelas::factory()->create(['nama' => '1A', 'tingkat' => 1]);

    expect(array_column(app(MasukKelasHariIni::class)($this->kantor->id)['kelas'], 'nama'))->toBe(['7A'])
        ->and(app(MasukKelasHariIni::class)()['kelas'])->toHaveCount(2);
});

test('tanggal lampau membaca absen hari itu dan kelas tanpa guru langsung kosong', function () {
    $terisi = kelasDi($this->kantor, '7A', 7);
    kelasDi($this->kantor, '8A', 8);
    AbsensiKelas::factory()->create(['kelas_id' => $terisi->id, 'tanggal' => '2026-09-04']);
    // Absen hari ini tidak boleh ikut terbaca untuk Jumat lalu.
    AbsensiKelas::factory()->create(['kelas_id' => kelasDi($this->kantor, '9A', 9)->id]);

    $hasil = app(MasukKelasHariIni::class)(null, Carbon::parse('2026-09-04'));

    expect($hasil['lewat'])->toBeTrue()
        ->and(array_column($hasil['kelas'], 'status', 'nama'))->toBe(['7A' => 'tepat', '8A' => 'kosong', '9A' => 'kosong']);
});
