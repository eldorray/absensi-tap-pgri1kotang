<?php

namespace App\Actions\Absensi;

use App\Models\Izin;
use App\Models\User;
use App\Notifications\PushAdmin;
use Illuminate\Http\UploadedFile;

/**
 * Pengajuan izin, sakit, atau cuti oleh pegawai sendiri, dari web maupun API.
 */
class AjukanIzin
{
    /**
     * @param  array{tipe: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string}  $data  Sudah divalidasi AjukanIzinRequest.
     */
    public function __invoke(User $pegawai, array $data, ?UploadedFile $lampiran): Izin
    {
        $izin = Izin::create([
            ...$data,
            // Disk 'local' bukan 'public': surat dokter tidak boleh bisa dibuka
            // dengan menebak URL.
            'lampiran_path' => $lampiran?->store('izin', 'local'),
            'user_id' => $pegawai->id,
        ]);

        PushAdmin::dariIzinGuru($izin)->kirimKeAdmin();

        return $izin;
    }

    /**
     * Daftar pengajuan milik pegawai itu sendiri, terbaru di atas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function daftar(User $pegawai): array
    {
        return Izin::query()
            ->where('user_id', $pegawai->id)
            ->orderByDesc('tanggal_mulai')
            ->get()
            ->map(fn (Izin $izin): array => [
                'id' => $izin->id,
                'tipe' => $izin->tipe->value,
                'tanggal_mulai' => $izin->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $izin->tanggal_selesai->toDateString(),
                'alasan' => $izin->alasan,
                'status' => $izin->status->value,
                'catatan_review' => $izin->catatan_review,
                'ada_lampiran' => $izin->lampiran_path !== null,
            ])
            ->values()
            ->all();
    }
}
