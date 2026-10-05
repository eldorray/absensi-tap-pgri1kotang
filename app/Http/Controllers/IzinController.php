<?php

namespace App\Http\Controllers;

use App\Actions\Absensi\AjukanIzin;
use App\Http\Requests\AjukanIzinRequest;
use App\Models\Izin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IzinController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('izin/Index', [
            'izins' => AjukanIzin::daftar($request->user()),
        ]);
    }

    public function store(AjukanIzinRequest $request, AjukanIzin $ajukan): RedirectResponse
    {
        /** @var array{tipe: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string} $data */
        $data = $request->safe()->only(['tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);

        $ajukan($request->user(), $data, $request->file('lampiran'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan izin terkirim.']);

        return to_route('izin.index');
    }

    /**
     * Unduh lampiran. Otorisasi lewat IzinPolicy::view, bukan lewat URL rahasia.
     */
    public function lampiran(Izin $izin): StreamedResponse
    {
        abort_if($izin->lampiran_path === null, 404);

        return Storage::disk('local')->download($izin->lampiran_path);
    }
}
