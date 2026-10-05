<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\CatatAbsensi;
use App\Actions\Absensi\DataBerandaGuru;
use App\Enums\TipeTap;
use App\Http\Requests\CatatAbsensiRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AbsensiController extends Controller
{
    /**
     * Halaman tap milik guru: jadwal dan status hari ini.
     */
    public function index(Request $request, DataBerandaGuru $dataBeranda): Response
    {
        $uuidPerangkat = $request->cookie('perangkat_uuid');
        $uuidPerangkat = is_string($uuidPerangkat) ? $uuidPerangkat : null;

        return Inertia::render('Dashboard', [
            ...$dataBeranda($request->user(), $uuidPerangkat),
            'perangkatUuidTersimpan' => $uuidPerangkat,
        ]);
    }

    /**
     * Terima satu tap. Semua gerbang penolak ada di CatatAbsensi.
     */
    public function store(CatatAbsensiRequest $request, CatatAbsensi $catat): RedirectResponse
    {
        $tipe = TipeTap::from($request->string('tipe')->toString());

        $absensi = $catat(
            $request->user(),
            $tipe,
            (float) $request->input('latitude'),
            (float) $request->input('longitude'),
            (int) round((float) $request->input('accuracy')),
            $request->string('device_uuid')->toString(),
            $this->passkeyTerverifikasi($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => CatatAbsensi::pesanTercatat($absensi, $tipe)]);

        return to_route('dashboard');
    }

    /**
     * Apakah ada verifikasi biometrik yang masih segar? Penanda sekali pakai:
     * di-pull, bukan di-get, supaya satu verifikasi tidak bisa dipakai untuk
     * dua tap.
     */
    private function passkeyTerverifikasi(Request $request): bool
    {
        $ditandai = $request->session()->pull(CatatAbsensi::KEY_VERIFIKASI);

        if (! is_string($ditandai)) {
            return false;
        }

        return Carbon::parse($ditandai)->diffInSeconds(now()) <= CatatAbsensi::UMUR_VERIFIKASI_DETIK;
    }
}
