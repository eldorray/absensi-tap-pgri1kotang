<?php

namespace App\Actions\Absensi;

use App\Models\AbsensiKelas;
use App\Models\JadwalKerja;
use App\Models\Kelas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Rekap masuk kelas per kelas untuk satu periode.
 *
 * Hari efektif = tanggal yang absen kelasnya berlaku dan batasnya sudah lewat,
 * memakai konfigurasi jam saat ini, mulai dari hari absen kelas pertama kali
 * dipakai di tahun ajaran ini. Status tiap hari mengikuti absen pertama
 * dan menit_terlambat yang tersimpan, sama seperti MasukKelasHariIni.
 */
class RekapMasukKelas
{
    /**
     * @return list<array{
     *     kelas_id: int,
     *     nama: string,
     *     unit: string,
     *     hari_efektif: int,
     *     tepat: int,
     *     telat: int,
     *     kosong: int,
     *     persentase: float,
     *     rincian: list<array{
     *         tanggal: string,
     *         status: 'tepat'|'telat'|'kosong',
     *         guru: list<array{nama: string, jam: string, menitTerlambat: int, absensiKelasId: int, adaFoto: bool}>
     *     }>
     * }>
     */
    public function __invoke(Carbon $mulai, Carbon $selesai, ?int $kantorId = null): array
    {
        // Hari sebelum fitur dipakai bukan "kosong": tanpa batas awal ini, rekap
        // pertama sesudah admin mengisi jam menghitung seluruh bulan lalu kosong.
        $dipakaiSejak = AbsensiKelas::query()->min('tanggal');

        $batas = $dipakaiSejak === null ? [] : array_filter(
            JadwalKerja::batasMasukKelasPeriode(
                $mulai->copy()->max(Carbon::parse((string) $dipakaiSejak)),
                $selesai->copy()->min(Carbon::today()),
            ),
            fn (CarbonImmutable $batas): bool => now()->greaterThanOrEqualTo($batas->addMinute()),
        );

        $absens = AbsensiKelas::query()
            ->with('user:id,name')
            ->whereBetween('tanggal', [$mulai->copy()->startOfDay(), $selesai->copy()->endOfDay()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (AbsensiKelas $absen): string => $absen->kelas_id.'|'.$absen->tanggal->toDateString());

        return array_values(Kelas::query()
            ->with('kantor:id,nama')
            ->where('is_active', true)
            ->when($kantorId !== null, fn ($query) => $query->where('kantor_id', $kantorId))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get()
            ->map(function (Kelas $kelas) use ($batas, $absens): array {
                $hitung = ['tepat' => 0, 'telat' => 0, 'kosong' => 0];
                $rincian = [];

                foreach (array_keys($batas) as $tanggal) {
                    $baris = $absens->get($kelas->id.'|'.$tanggal, collect());
                    /** @var AbsensiKelas|null $pertama */
                    $pertama = $baris->first();
                    $status = match (true) {
                        $pertama === null => 'kosong',
                        $pertama->menit_terlambat > 0 => 'telat',
                        default => 'tepat',
                    };
                    $hitung[$status]++;
                    $rincian[] = [
                        'tanggal' => (string) $tanggal,
                        'status' => $status,
                        'guru' => array_values($baris->map(fn (AbsensiKelas $absen): array => $absen->ringkasan())->all()),
                    ];
                }

                $hariEfektif = count($batas);

                return [
                    'kelas_id' => $kelas->id,
                    'nama' => $kelas->nama,
                    'unit' => $kelas->kantor->nama,
                    'hari_efektif' => $hariEfektif,
                    'tepat' => $hitung['tepat'],
                    'telat' => $hitung['telat'],
                    'kosong' => $hitung['kosong'],
                    'persentase' => $hariEfektif === 0 ? 0.0 : round($hitung['tepat'] / $hariEfektif * 100, 2),
                    'rincian' => $rincian,
                ];
            })
            ->all());
    }
}
