<?php

namespace App\Actions\OrangTua;

use App\Enums\StatusIzin;
use App\Models\IzinOrangTua;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Notifications\PushAdmin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Pengajuan izin atau sakit untuk anak, oleh orang tuanya, dari web maupun API.
 */
class AjukanIzinAnak
{
    /**
     * @param  array{siswa_id: int|string, tipe: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string}  $data  Sudah divalidasi OrangTua\AjukanIzinRequest.
     *
     * @throws ValidationException
     */
    public function __invoke(User $orangTua, array $data, ?UploadedFile $lampiran): IzinOrangTua
    {
        $lampiranPath = $lampiran?->store('izin-orang-tua', 'local');
        $data['lampiran_path'] = $lampiranPath;
        $data['user_id'] = $orangTua->id;
        $data['nama_pengaju'] = $orangTua->name;
        $data['email_pengaju'] = $orangTua->email;

        try {
            $izin = DB::transaction(function () use ($data): IzinOrangTua {
                Siswa::query()->whereKey($data['siswa_id'])->lockForUpdate()->firstOrFail();

                $bertabrakan = IzinOrangTua::query()
                    ->where('siswa_id', $data['siswa_id'])
                    ->whereIn('status', [StatusIzin::Pending, StatusIzin::Disetujui])
                    ->where('tanggal_mulai', '<=', $data['tanggal_selesai'])
                    ->where('tanggal_selesai', '>=', $data['tanggal_mulai'])
                    ->exists();

                if ($bertabrakan) {
                    throw ValidationException::withMessages([
                        'tanggal_mulai' => 'Sudah ada pengajuan untuk anak ini pada rentang tanggal tersebut.',
                    ]);
                }

                return IzinOrangTua::create($data);
            });
        } catch (Throwable $throwable) {
            if (is_string($lampiranPath)) {
                Storage::disk('local')->delete($lampiranPath);
            }

            throw $throwable;
        }

        PushAdmin::dariIzinOrangTua($izin)->kirimKeAdmin();

        return $izin;
    }

    /**
     * Isi halaman izin orang tua: batas tanggal, anak aktif, dan pengajuannya.
     *
     * @return array<string, mixed>
     */
    public static function halaman(User $orangTua): array
    {
        $tahunAjaran = TahunAjaran::aktif();

        return [
            'tanggalMinimum' => today()->toDateString(),
            'tanggalMaximum' => $tahunAjaran?->tanggal_selesai->toDateString(),
            'anak' => $orangTua->siswas()
                ->where('siswas.is_active', true)
                ->orderBy('nama')
                ->get(['siswas.id', 'siswas.nama', 'siswas.nis'])
                ->map(fn (Siswa $siswa): array => [
                    'id' => $siswa->id,
                    'nama' => $siswa->nama,
                    'nis' => $siswa->nis,
                ])
                ->values(),
            'izins' => IzinOrangTua::query()
                ->where('user_id', $orangTua->id)
                ->with('siswa:id,nama,nis')
                ->latest()
                ->get()
                ->map(fn (IzinOrangTua $izin): array => [
                    'id' => $izin->id,
                    'siswa' => $izin->siswa->nama,
                    'nis' => $izin->siswa->nis,
                    'tipe' => $izin->tipe->value,
                    'tanggal_mulai' => $izin->tanggal_mulai->toDateString(),
                    'tanggal_selesai' => $izin->tanggal_selesai->toDateString(),
                    'alasan' => $izin->alasan,
                    'status' => $izin->status->value,
                    'catatan_review' => $izin->catatan_review,
                    'ada_lampiran' => $izin->lampiran_path !== null,
                ])
                ->values(),
        ];
    }
}
