<?php

namespace App\Http\Controllers\Api;

use App\Actions\Absensi\CatatAbsensi;
use App\Actions\Absensi\DataBerandaGuru;
use App\Actions\Absensi\TapDitolak;
use App\Actions\Absensi\VerifikasiKunciPerangkat;
use App\Enums\TipeTap;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CatatAbsensiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    /**
     * Beranda tap guru. Uuid HP dikirim aplikasi supaya status HP ini ikut terbaca.
     */
    public function beranda(Request $request, DataBerandaGuru $dataBeranda): JsonResponse
    {
        $data = $request->validate(['device_uuid' => ['nullable', 'uuid']]);

        return response()->json($dataBeranda($request->user(), $data['device_uuid'] ?? null));
    }

    /**
     * Tantangan sekali pakai untuk ditandatangani kunci perangkat setelah sidik jari.
     */
    public function tantangan(Request $request, VerifikasiKunciPerangkat $verifikasi): JsonResponse
    {
        return response()->json([
            'tantangan' => $verifikasi->terbitkan($request->user()),
            'berlaku_detik' => CatatAbsensi::UMUR_VERIFIKASI_DETIK,
        ]);
    }

    /**
     * Terima satu tap. Semua gerbang penolak ada di CatatAbsensi, sama dengan web.
     */
    public function store(CatatAbsensiRequest $request, CatatAbsensi $catat, VerifikasiKunciPerangkat $verifikasi): JsonResponse
    {
        $guru = $request->user();
        $tipe = TipeTap::from($request->string('tipe')->toString());
        $uuid = $request->string('device_uuid')->toString();

        try {
            $absensi = $catat(
                $guru,
                $tipe,
                (float) $request->input('latitude'),
                (float) $request->input('longitude'),
                (int) round((float) $request->input('accuracy')),
                $uuid,
                $verifikasi->sah(
                    $guru,
                    $uuid,
                    $request->filled('tantangan') ? $request->string('tantangan')->toString() : null,
                    $request->filled('tanda_tangan') ? $request->string('tanda_tangan')->toString() : null,
                ),
            );
        } catch (TapDitolak $ditolak) {
            return response()->json([
                'message' => $ditolak->getMessage(),
                'errors' => $ditolak->errors(),
                'kode' => $ditolak->hasil->value,
            ], 422);
        }

        $absensi->load(['masukAttempt', 'pulangAttempt']);

        return response()->json([
            'pesan' => CatatAbsensi::pesanTercatat($absensi, $tipe),
            'absensi' => [
                'status' => $absensi->status?->value,
                'menit_terlambat' => $absensi->menit_terlambat,
                'pulang_cepat' => $absensi->pulang_cepat,
                'jam_masuk' => $absensi->masukAttempt?->created_at?->format('H:i'),
                'jam_pulang' => $absensi->pulangAttempt?->created_at?->format('H:i'),
                'terverifikasi' => $absensi->masukAttempt->terverifikasi ?? false,
            ],
        ]);
    }
}
