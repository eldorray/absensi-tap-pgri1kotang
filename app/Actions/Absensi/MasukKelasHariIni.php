<?php

namespace App\Actions\Absensi;

use App\Models\AbsensiKelas;
use App\Models\JadwalKerja;
use App\Models\Kelas;

/**
 * Keadaan absen masuk kelas hari ini, per kelas.
 *
 * Dipakai beranda guru (memilih kelas), dashboard admin (memantau kelas
 * kosong), dan push kelas kosong -- supaya ketiganya membaca aturan status
 * yang sama. Status memakai menit_terlambat yang tersimpan, jadi tidak
 * berubah kalau admin mengganti jam batas sesudah guru absen.
 */
class MasukKelasHariIni
{
    /**
     * @return array{
     *     batas: string,
     *     lewat: bool,
     *     kelas: list<array{
     *         id: int,
     *         nama: string,
     *         unit: string,
     *         status: 'tepat'|'telat'|'kosong'|'menunggu',
     *         guru: list<array{nama: string, jam: string, menitTerlambat: int, absensiKelasId: int, adaFoto: bool}>
     *     }>
     * }|null null kalau hari ini tanpa absen masuk kelas
     */
    public function __invoke(?int $kantorId = null): ?array
    {
        $batas = JadwalKerja::batasMasukKelas(today());

        if ($batas === null) {
            return null;
        }

        // Kelas dihitung kosong mulai menit yang sama dengan saat
        // menit_terlambat pertama kali bernilai 1.
        $lewat = now()->greaterThanOrEqualTo($batas->addMinute());

        $absens = AbsensiKelas::query()
            ->with('user:id,name')
            ->whereDate('tanggal', today())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('kelas_id');

        $kelas = array_values(Kelas::query()
            ->with('kantor:id,nama')
            ->where('is_active', true)
            ->when($kantorId !== null, fn ($query) => $query->where('kantor_id', $kantorId))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get()
            ->map(function (Kelas $kelas) use ($absens, $lewat): array {
                $baris = $absens->get($kelas->id, collect());
                /** @var AbsensiKelas|null $pertama */
                $pertama = $baris->first();

                return [
                    'id' => $kelas->id,
                    'nama' => $kelas->nama,
                    'unit' => $kelas->kantor->nama,
                    'status' => match (true) {
                        $pertama !== null => $pertama->menit_terlambat > 0 ? 'telat' : 'tepat',
                        $lewat => 'kosong',
                        default => 'menunggu',
                    },
                    'guru' => array_values($baris->map(fn (AbsensiKelas $absen): array => $absen->ringkasan())->all()),
                ];
            })
            ->all());

        return ['batas' => $batas->format('H:i'), 'lewat' => $lewat, 'kelas' => $kelas];
    }
}
