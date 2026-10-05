<?php

namespace App\Http\Controllers\Api;

use App\Actions\Absensi\AjukanIzin;
use App\Actions\Absensi\CatatMasukKelas;
use App\Actions\Absensi\DaftarkanPerangkat;
use App\Actions\Absensi\DataRiwayat;
use App\Http\Controllers\Controller;
use App\Http\Requests\AjukanIzinRequest;
use App\Http\Requests\Api\DaftarkanPerangkatRequest;
use App\Http\Requests\CatatMasukKelasRequest;
use App\Http\Requests\LihatRiwayatRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Wilayah pegawai di luar tap: HP, masuk kelas, riwayat, dan izin. Setiap
 * aksi memanggil Action yang sama dengan halaman web padanannya.
 */
class PegawaiController extends Controller
{
    public function daftarkanPerangkat(DaftarkanPerangkatRequest $request, DaftarkanPerangkat $daftarkan): JsonResponse
    {
        $perangkat = $daftarkan(
            $request->user(),
            $request->string('device_uuid')->toString(),
            $request->userAgent(),
            $request->kunciPem(),
        );

        return response()->json([
            'perangkat' => [
                'uuid' => $perangkat->uuid,
                'status' => $perangkat->status->value,
                'label' => $perangkat->label,
                'punya_kunci' => $perangkat->kunci_publik !== null,
            ],
        ], $perangkat->wasRecentlyCreated ? 201 : 200);
    }

    public function masukKelas(CatatMasukKelasRequest $request, CatatMasukKelas $catat): JsonResponse
    {
        /** @var UploadedFile $foto */
        $foto = $request->file('foto');

        $absen = $catat(
            $request->user(),
            $request->integer('kelas_id'),
            $foto,
            $request->string('device_uuid')->toString(),
        );

        return response()->json([
            'pesan' => CatatMasukKelas::pesanTercatat($absen),
            'absen' => [
                'kelas' => $absen->kelas->nama,
                'jam' => $absen->created_at?->format('H:i') ?? '',
                'menitTerlambat' => $absen->menit_terlambat,
            ],
        ], 201);
    }

    public function riwayat(LihatRiwayatRequest $request, DataRiwayat $dataRiwayat): JsonResponse
    {
        return response()->json($dataRiwayat($request->user(), $request->bulan()));
    }

    public function izin(Request $request): JsonResponse
    {
        return response()->json(['izins' => AjukanIzin::daftar($request->user())]);
    }

    public function ajukanIzin(AjukanIzinRequest $request, AjukanIzin $ajukan): JsonResponse
    {
        /** @var array{tipe: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string} $data */
        $data = $request->safe()->only(['tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);

        $izin = $ajukan($request->user(), $data, $request->file('lampiran'));

        return response()->json(['pesan' => 'Pengajuan izin terkirim.', 'izin' => ['id' => $izin->id]], 201);
    }
}
