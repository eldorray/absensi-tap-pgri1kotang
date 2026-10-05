<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Absensi\CatatMasukKelas;
use App\Http\Controllers\Controller;
use App\Models\AbsensiKelas;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasukKelasController extends Controller
{
    /**
     * Foto bukti masuk kelas. Disimpan di disk privat; route admin ini satu-
     * satunya jalan membukanya, jadi foto anak tidak punya URL publik.
     */
    public function foto(AbsensiKelas $absensiKelas): StreamedResponse
    {
        $disk = Storage::disk(CatatMasukKelas::DISK);

        abort_if($absensiKelas->foto_path === null || ! $disk->exists($absensiKelas->foto_path), 404);

        return $disk->response($absensiKelas->foto_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
