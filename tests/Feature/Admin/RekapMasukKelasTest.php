<?php

use App\Actions\Absensi\RekapMasukKelas;
use App\Models\AbsensiKelas;
use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\Kelas;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Rabu. Senin-Sabtu hari kerja; jam masuk kelas hanya Senin-Jumat.
    Carbon::setTestNow('2026-09-09 10:00:00');
    $this->seed(JadwalKerjaSeeder::class);
    JadwalKerja::query()->whereNull('user_id')->whereBetween('day_of_week', [1, 5])->update(['jam_masuk_kelas' => '06:50:00']);
    $this->kelas = Kelas::factory()->create(['nama' => '7A', 'tingkat' => 7]);
});

test('rekap per kelas menghitung tepat, telat, dan kosong pada hari efektif', function () {
    // Periode 7-12 Sep: efektif Senin 7, Selasa 8, Rabu 9 (batas sudah lewat).
    // Kamis-Jumat belum tiba, Sabtu tanpa jam masuk kelas.
    AbsensiKelas::factory()->create(['kelas_id' => $this->kelas->id, 'tanggal' => '2026-09-07']);
    AbsensiKelas::factory()->telat(4)->create(['kelas_id' => $this->kelas->id, 'tanggal' => '2026-09-08']);

    $baris = app(RekapMasukKelas::class)(Carbon::parse('2026-09-07'), Carbon::parse('2026-09-12'))[0];

    expect($baris['nama'])->toBe('7A')
        ->and($baris['hari_efektif'])->toBe(3)
        ->and([$baris['tepat'], $baris['telat'], $baris['kosong']])->toBe([1, 1, 1])
        ->and($baris['persentase'])->toBe(33.33)
        ->and(array_column($baris['rincian'], 'status', 'tanggal'))->toBe([
            '2026-09-07' => 'tepat',
            '2026-09-08' => 'telat',
            '2026-09-09' => 'kosong',
        ]);
});

test('hari libur bukan hari efektif rekap per kelas', function () {
    // Jangkar: fitur sudah dipakai sejak Jumat sebelumnya.
    AbsensiKelas::factory()->create(['kelas_id' => $this->kelas->id, 'tanggal' => '2026-09-04']);
    HariLibur::factory()->create(['tanggal' => '2026-09-08']);

    $baris = app(RekapMasukKelas::class)(Carbon::parse('2026-09-07'), Carbon::parse('2026-09-08'))[0];

    expect($baris['hari_efektif'])->toBe(1)
        ->and($baris['kosong'])->toBe(1);
});

test('hari ini belum dihitung sebelum batasnya lewat', function () {
    Carbon::setTestNow('2026-09-09 06:30:00');

    $baris = app(RekapMasukKelas::class)(Carbon::parse('2026-09-09'), Carbon::parse('2026-09-09'))[0];

    expect($baris['hari_efektif'])->toBe(0)
        ->and($baris['persentase'])->toBe(0.0);
});

test('admin membuka tab rekap masuk kelas dan mengunduh CSV-nya', function () {
    // Jangkar: fitur sudah dipakai sejak Jumat sebelumnya.
    AbsensiKelas::factory()->create(['kelas_id' => $this->kelas->id, 'tanggal' => '2026-09-04']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.rekap.masuk-kelas', ['mode' => 'periode', 'mulai' => '2026-09-07', 'selesai' => '2026-09-09']))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/RekapMasukKelas')
            ->where('rekap.0.nama', '7A')
            ->where('rekap.0.kosong', 3));

    $isi = $this->actingAs($admin)
        ->get(route('admin.rekap.masuk-kelas.export', ['mode' => 'periode', 'mulai' => '2026-09-07', 'selesai' => '2026-09-09']))
        ->assertOk()
        ->streamedContent();

    expect($isi)->toContain('Unit,Kelas,"Hari efektif",Tepat,Telat,Kosong,"% Tepat"')
        ->and($isi)->toContain(',7A,3,0,0,3,0');
});

test('guru tidak boleh membuka rekap masuk kelas', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rekap.masuk-kelas'))->assertForbidden();
});

test('hari sebelum absen kelas pertama dipakai tidak dihitung kosong', function () {
    AbsensiKelas::factory()->create(['kelas_id' => $this->kelas->id, 'tanggal' => '2026-09-08']);

    $baris = app(RekapMasukKelas::class)(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-09'))[0];

    // Tanpa batas awal, 1-4 dan 7 September ikut dihitung kosong.
    expect($baris['hari_efektif'])->toBe(2)
        ->and(array_keys(array_column($baris['rincian'], 'status', 'tanggal')))->toBe(['2026-09-08', '2026-09-09']);
});

test('sebelum ada absen kelas sama sekali, rekap per kelas belum punya hari efektif', function () {
    $baris = app(RekapMasukKelas::class)(Carbon::parse('2026-09-07'), Carbon::parse('2026-09-09'))[0];

    expect($baris['hari_efektif'])->toBe(0)
        ->and($baris['kosong'])->toBe(0);
});
