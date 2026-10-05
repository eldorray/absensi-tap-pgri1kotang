<?php

namespace App\Http\Controllers\OrangTua;

use App\Actions\OrangTua\AjukanIzinAnak;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrangTua\AjukanIzinRequest;
use App\Models\IzinOrangTua;
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
        return Inertia::render('orang-tua/Izin', AjukanIzinAnak::halaman($request->user()));
    }

    public function store(AjukanIzinRequest $request, AjukanIzinAnak $ajukan): RedirectResponse
    {
        /** @var array{siswa_id: int|string, tipe: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string} $data */
        $data = $request->safe()->only(['siswa_id', 'tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);

        $ajukan($request->user(), $data, $request->file('lampiran'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan izin anak terkirim.']);

        return to_route('orang-tua.izin.index');
    }

    public function lampiran(IzinOrangTua $izinOrangTua): StreamedResponse
    {
        abort_if($izinOrangTua->lampiran_path === null, 404);

        return Storage::disk('local')->download($izinOrangTua->lampiran_path);
    }
}
