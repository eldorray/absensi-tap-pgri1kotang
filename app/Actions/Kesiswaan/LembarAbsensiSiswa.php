<?php

namespace App\Actions\Kesiswaan;

use App\Enums\StatusKehadiranSiswa;
use App\Enums\StatusSesiAbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\SesiAbsensiSiswa;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Daftar kelas dan lembar absensi siswa untuk halaman web dan API.
 *
 * Guru piket mengabsen seluruh sekolah; guru lain hanya membaca kelas yang
 * diampunya (wali atau pengganti) pada tanggal itu.
 */
class LembarAbsensiSiswa
{
    /**
     * @return array{kelas: array<int, array<string, mixed>>, tanggal: string, piket: bool}
     */
    public function daftarKelas(User $guru, Carbon $tanggal): array
    {
        $piket = $guru->can('piket');

        // Daftar piket diurut per unit agar bisa dijelajahi kelas demi kelas.
        $query = Kelas::query()->where('is_active', true)->with('kantor:id,nama');

        if ($piket) {
            $query->orderBy('kantor_id')->orderBy('tingkat')->orderBy('nama');
        } else {
            $query->diampuOleh($guru, $tanggal);
        }

        $kelas = $query->get()->map(function (Kelas $kelas) use ($tanggal): array {
            $sesi = SesiAbsensiSiswa::query()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->first();

            return ['id' => $kelas->id, 'nama' => $kelas->nama, 'kantor' => $kelas->kantor?->nama, 'jumlah_siswa' => AnggotaKelas::query()->where('kelas_id', $kelas->id)->berlakuPada($tanggal)->count(), 'status' => $sesi?->status->value ?? StatusSesiAbsensiSiswa::BelumDiperiksa->value, 'terakhir_disimpan' => $sesi?->updated_at?->toIso8601String()];
        })->values()->all();

        return ['kelas' => $kelas, 'tanggal' => $tanggal->toDateString(), 'piket' => $piket];
    }

    /**
     * Lembar satu kelas. 403 kalau guru bukan piket dan tidak mengampu kelas itu.
     *
     * @return array<string, mixed>
     */
    public function lembar(User $guru, Kelas $kelas, Carbon $tanggal): array
    {
        $piket = $guru->can('piket');

        abort_unless($piket || Kelas::query()->diampuOleh($guru, $tanggal)->whereKey($kelas->id)->exists(), 403);
        $sesi = SesiAbsensiSiswa::query()->where('kelas_id', $kelas->id)->whereDate('tanggal', $tanggal)->with('absensis')->first();
        $details = $sesi?->absensis->keyBy('siswa_id') ?? collect();
        $siswas = AnggotaKelas::query()->where('kelas_id', $kelas->id)->berlakuPada($tanggal)->with('siswa:id,nis,nama')->get()->sortBy(fn (AnggotaKelas $a) => $a->siswa?->nama)->values()->map(function (AnggotaKelas $a) use ($details): array {
            $detail = $details->get($a->siswa_id);

            return ['id' => $a->siswa_id, 'nis' => $a->siswa?->nis, 'nama' => $a->siswa?->nama, 'status' => $detail?->status->value ?? StatusKehadiranSiswa::Hadir->value, 'catatan' => $detail?->catatan, 'jam_datang' => $detail?->jam_datang];
        })->all();
        $status = $sesi === null ? StatusSesiAbsensiSiswa::BelumDiperiksa : $sesi->status;
        $terkunci = in_array($status, [StatusSesiAbsensiSiswa::Final, StatusSesiAbsensiSiswa::Dikoreksi], true);
        // Mengisi absensi hanya hak guru piket, dan hanya untuk hari ini.
        // Bagi guru, lembar ini selalu terbaca saja.
        $dapatMengisi = $piket && $tanggal->isToday() && ! $terkunci;
        $kelas->load('kantor:id,nama', 'tahunAjaran:id,nama');

        return ['kelas' => ['id' => $kelas->id, 'nama' => $kelas->nama, 'kantor' => $kelas->kantor?->nama, 'tahun_ajaran' => $kelas->tahunAjaran?->nama], 'tanggal' => $tanggal->toDateString(), 'siswa' => $siswas, 'piket' => $piket, 'sesi' => ['status' => $status->value, 'catatan' => $sesi?->catatan, 'read_only' => ! $dapatMengisi, 'dapat_mengisi' => $dapatMengisi, 'dapat_dibuka' => $piket && $tanggal->isToday() && $status === StatusSesiAbsensiSiswa::Final]];
    }
}
