<?php

use App\Models\HariLibur;
use App\Models\JadwalKerja;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // 2026-09-07 Senin; 2026-09-06 Minggu (bukan hari kerja di seeder).
    Carbon::setTestNow('2026-09-07 06:00:00');
    $this->seed(JadwalKerjaSeeder::class);
});

function aturJamMasukKelas(int $hari, ?string $jam): void
{
    JadwalKerja::query()
        ->whereNull('user_id')
        ->where('day_of_week', $hari)
        ->update(['jam_masuk_kelas' => $jam]);
}

test('batas masuk kelas diambil dari jadwal default hari itu', function () {
    aturJamMasukKelas(1, '06:50:00');

    expect(JadwalKerja::batasMasukKelas(today())?->format('Y-m-d H:i:s'))
        ->toBe('2026-09-07 06:50:00');
});

test('jam masuk kelas kosong berarti hari itu tanpa absen kelas', function () {
    expect(JadwalKerja::batasMasukKelas(today()))->toBeNull();
});

test('hari libur dan hari non-kerja tidak punya batas masuk kelas', function () {
    aturJamMasukKelas(0, '06:50:00');
    aturJamMasukKelas(1, '06:50:00');
    HariLibur::factory()->create(['tanggal' => '2026-09-14']);

    expect(JadwalKerja::batasMasukKelas(Carbon::parse('2026-09-06')))->toBeNull()
        ->and(JadwalKerja::batasMasukKelas(Carbon::parse('2026-09-14')))->toBeNull();
});

test('jam masuk kelas di jadwal milik guru diabaikan', function () {
    JadwalKerja::create([
        'user_id' => User::factory()->create()->id,
        'day_of_week' => 1,
        'jam_masuk' => '08:00:00',
        'jam_pulang' => '14:00:00',
        'jam_masuk_kelas' => '08:00:00',
        'is_hari_kerja' => true,
    ]);

    expect(JadwalKerja::batasMasukKelas(today()))->toBeNull();
});

test('batas per periode hanya memuat tanggal yang berlaku', function () {
    aturJamMasukKelas(1, '06:50:00');
    aturJamMasukKelas(2, '07:00:00');
    HariLibur::factory()->create(['tanggal' => '2026-09-14']);

    $batas = JadwalKerja::batasMasukKelasPeriode(Carbon::parse('2026-09-07'), Carbon::parse('2026-09-15'));

    expect(array_keys($batas))->toBe(['2026-09-07', '2026-09-08', '2026-09-15'])
        ->and($batas['2026-09-08']->format('H:i'))->toBe('07:00');
});
