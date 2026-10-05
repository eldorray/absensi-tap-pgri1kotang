<?php

namespace App\Http\Controllers\Api;

use App\Actions\OrangTua\AjukanIzinAnak;
use App\Actions\OrangTua\DataKehadiranAnak;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrangTua\AjukanIzinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrangTuaController extends Controller
{
    public function kehadiran(Request $request, DataKehadiranAnak $dataKehadiran): JsonResponse
    {
        return response()->json($dataKehadiran($request->user(), $request->integer('siswa')));
    }

    public function izin(Request $request): JsonResponse
    {
        return response()->json(AjukanIzinAnak::halaman($request->user()));
    }

    public function ajukanIzin(AjukanIzinRequest $request, AjukanIzinAnak $ajukan): JsonResponse
    {
        /** @var array{siswa_id: int|string, tipe: string, tanggal_mulai: string, tanggal_selesai: string, alasan: string} $data */
        $data = $request->safe()->only(['siswa_id', 'tipe', 'tanggal_mulai', 'tanggal_selesai', 'alasan']);

        $izin = $ajukan($request->user(), $data, $request->file('lampiran'));

        return response()->json(['pesan' => 'Pengajuan izin anak terkirim.', 'izin' => ['id' => $izin->id]], 201);
    }
}
