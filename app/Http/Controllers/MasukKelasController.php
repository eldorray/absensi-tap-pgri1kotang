<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\CatatMasukKelas;
use App\Http\Requests\CatatMasukKelasRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

class MasukKelasController extends Controller
{
    /**
     * Terima satu absen masuk kelas. Semua gerbang penolak ada di CatatMasukKelas.
     */
    public function store(CatatMasukKelasRequest $request, CatatMasukKelas $catat): RedirectResponse
    {
        /** @var UploadedFile $foto */
        $foto = $request->file('foto');

        $absen = $catat(
            $request->user(),
            $request->integer('kelas_id'),
            $foto,
            $request->string('device_uuid')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf(
            'Masuk %s tercatat pukul %s.%s',
            $absen->kelas->nama,
            now()->format('H.i'),
            $absen->menit_terlambat > 0 ? " Telat {$absen->menit_terlambat} menit." : ' Tepat waktu.',
        )]);

        return to_route('dashboard');
    }
}
