<?php

namespace App\Actions\OrangTua;

use App\Enums\StatusKehadiranSiswa;
use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\SesiAbsensiSiswa;
use App\Models\Siswa;
use App\Models\User;

/**
 * Kehadiran anak yang ditautkan ke seorang orang tua, untuk portal web dan API.
 *
 * Hanya anak milik orang tua itu sendiri yang bisa terpilih: id siswa lain
 * diabaikan dan jatuh ke anak pertama, bukan membuka data anak orang lain.
 */
class DataKehadiranAnak
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $orangTua, int $siswaId): array
    {
        $siswas = $orangTua->siswas()
            ->with('kantor:id,nama')
            ->orderBy('nama')
            ->get([
                'siswas.id',
                'siswas.nama',
                'siswas.nis',
                'siswas.nisn',
                'siswas.tempat_lahir',
                'siswas.tanggal_lahir',
                'siswas.kantor_id',
                'siswas.foto',
                'siswas.is_active',
            ]);

        $siswaTerpilih = $siswas->firstWhere('id', $siswaId) ?? $siswas->first();
        $riwayat = collect();
        $kelas = null;

        if ($siswaTerpilih instanceof Siswa) {
            $riwayat = AbsensiSiswa::query()
                ->where('siswa_id', $siswaTerpilih->id)
                ->whereHas('sesi', fn ($query) => $query->terpublikasi())
                ->with(['sesi' => fn ($query) => $query->terpublikasi()->with('kelas:id,nama')])
                ->orderByDesc(
                    SesiAbsensiSiswa::query()
                        ->select('tanggal')
                        ->whereColumn('sesi_absensi_siswas.id', 'absensi_siswas.sesi_absensi_siswa_id')
                        ->limit(1)
                )
                ->limit(60)
                ->get();

            $kelas = AnggotaKelas::query()
                ->where('siswa_id', $siswaTerpilih->id)
                ->berlakuPada(today())
                ->with('kelas:id,nama')
                ->first()?->kelas?->nama;
        }

        $ringkasan = collect(StatusKehadiranSiswa::cases())
            ->mapWithKeys(fn (StatusKehadiranSiswa $status): array => [
                $status->value => $riwayat->where('status', $status)->count(),
            ])->all();

        return [
            'anak' => $siswas->map(fn (Siswa $siswa): array => [
                'id' => $siswa->id,
                'nama' => $siswa->nama,
                'nis' => $siswa->nis,
                'unit' => $siswa->kantor?->nama,
                'foto' => $siswa->foto,
                'is_active' => $siswa->is_active,
            ])->values(),
            'siswaTerpilih' => $siswaTerpilih instanceof Siswa ? [
                'id' => $siswaTerpilih->id,
                'nama' => $siswaTerpilih->nama,
                'nis' => $siswaTerpilih->nis,
                'nisn' => $siswaTerpilih->nisn,
                'tempat_lahir' => $siswaTerpilih->tempat_lahir,
                'tanggal_lahir' => $siswaTerpilih->tanggal_lahir?->toDateString(),
                'tanggal_lahir_label' => $siswaTerpilih->tanggal_lahir?->locale('id')->translatedFormat('d F Y'),
                'unit' => $siswaTerpilih->kantor?->nama,
                'kelas' => $kelas,
                'foto' => $siswaTerpilih->foto,
            ] : null,
            'ringkasan' => $ringkasan,
            'riwayat' => $riwayat->map(fn (AbsensiSiswa $absensi): array => [
                'id' => $absensi->id,
                'tanggal' => $absensi->sesi->tanggal->toDateString(),
                'tanggal_label' => $absensi->sesi->tanggal->translatedFormat('l, d F Y'),
                'kelas' => $absensi->sesi->kelas?->nama,
                'status' => $absensi->status->value,
                'status_label' => $absensi->status->label(),
                'jam_datang' => $absensi->jam_datang,
                'catatan' => $absensi->catatan,
            ])->values(),
        ];
    }
}
