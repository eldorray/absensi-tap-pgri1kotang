<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\DataRiwayat;
use App\Http\Requests\LihatRiwayatRequest;
use Inertia\Inertia;
use Inertia\Response;

class RiwayatAbsensiController extends Controller
{
    /**
     * Riwayat satu bulan milik pengguna yang sedang login.
     */
    public function index(LihatRiwayatRequest $request, DataRiwayat $dataRiwayat): Response
    {
        return Inertia::render('riwayat/Index', $dataRiwayat($request->user(), $request->bulan()));
    }
}
