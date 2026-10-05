<?php

namespace App\Http\Controllers\Api;

use App\Actions\Kesiswaan\LembarAbsensiSiswa;
use App\Actions\Kesiswaan\SimpanAbsensiSiswa;
use App\Http\Controllers\Controller;
use App\Http\Requests\PilihTanggalAbsensiSiswaRequest;
use App\Http\Requests\SimpanAbsensiSiswaRequest;
use App\Models\Kelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AbsensiSiswaController extends Controller
{
    public function index(PilihTanggalAbsensiSiswaRequest $request, LembarAbsensiSiswa $lembar): JsonResponse
    {
        return response()->json($lembar->daftarKelas($request->user(), $request->tanggal()));
    }

    public function show(PilihTanggalAbsensiSiswaRequest $request, Kelas $kelas, LembarAbsensiSiswa $lembar): JsonResponse
    {
        return response()->json($lembar->lembar($request->user(), $kelas, $request->tanggal()));
    }

    public function draft(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $simpan, LembarAbsensiSiswa $lembar): JsonResponse
    {
        return $this->simpan($request, $kelas, $simpan, $lembar, false);
    }

    public function finalisasi(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $simpan, LembarAbsensiSiswa $lembar): JsonResponse
    {
        return $this->simpan($request, $kelas, $simpan, $lembar, true);
    }

    /**
     * Simpan lalu kembalikan lembar terbaru, supaya aplikasi tidak perlu
     * meminta ulang untuk tahu status sesinya.
     */
    private function simpan(SimpanAbsensiSiswaRequest $request, Kelas $kelas, SimpanAbsensiSiswa $simpan, LembarAbsensiSiswa $lembar, bool $final): JsonResponse
    {
        /** @var array{tanggal: string, catatan?: string|null, absensis: list<array{siswa_id: int, status: string, catatan?: string|null, jam_datang?: string|null}>} $data */
        $data = $request->validated();
        $tanggal = Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay();

        $simpan->execute($kelas, $request->user(), $tanggal, $data, $final);

        return response()->json([
            'pesan' => $final ? 'Absensi berhasil difinalisasi.' : 'Draft berhasil disimpan.',
            ...$lembar->lembar($request->user(), $kelas, $tanggal),
        ]);
    }
}
