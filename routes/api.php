<?php

use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\AbsensiSiswaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrangTuaController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Middleware\PastikanAkunAktif;
use Illuminate\Support\Facades\Route;

/*
 * API untuk aplikasi Android. Autentikasi dengan token Sanctum; setiap gerbang
 * (role, piket, throttle) sama dengan route web padanannya di routes/web.php.
 *
 * Throttle diberi prefix sendiri-sendiri: tanpa prefix, semua throttle:N,M
 * berbagi satu hitungan per pengguna, sehingga permintaan biasa ikut
 * menghabiskan jatah tap.
 */
Route::name('api.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware(['auth:sanctum', PastikanAkunAktif::class, 'throttle:120,1,api'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::middleware('can:pegawai')->group(function (): void {
            Route::get('beranda', [AbsensiController::class, 'beranda'])->name('beranda');
            Route::get('absensi/tantangan', [AbsensiController::class, 'tantangan'])
                ->middleware('throttle:20,1,tantangan')
                ->name('absensi.tantangan');
            Route::post('absensi', [AbsensiController::class, 'store'])
                ->middleware('throttle:10,1,tap')
                ->name('absensi.store');

            Route::post('perangkat', [PegawaiController::class, 'daftarkanPerangkat'])->name('perangkat.store');
            Route::post('masuk-kelas', [PegawaiController::class, 'masukKelas'])
                ->middleware('throttle:10,1,masuk-kelas')
                ->name('masuk-kelas.store');
            Route::get('riwayat', [PegawaiController::class, 'riwayat'])->name('riwayat');
            Route::get('izin', [PegawaiController::class, 'izin'])->name('izin.index');
            Route::post('izin', [PegawaiController::class, 'ajukanIzin'])
                ->middleware('throttle:10,1,izin')
                ->name('izin.store');

            Route::get('absensi-siswa', [AbsensiSiswaController::class, 'index'])->name('absensi-siswa.index');
            Route::get('absensi-siswa/{kelas}', [AbsensiSiswaController::class, 'show'])->name('absensi-siswa.show');

            // Mengisi absensi siswa adalah pekerjaan guru piket; guru hanya membaca.
            Route::middleware('can:piket')->group(function (): void {
                Route::put('absensi-siswa/{kelas}/draft', [AbsensiSiswaController::class, 'draft'])->name('absensi-siswa.draft');
                Route::put('absensi-siswa/{kelas}/finalisasi', [AbsensiSiswaController::class, 'finalisasi'])->name('absensi-siswa.finalisasi');
            });
        });

        Route::middleware('can:orang-tua')->prefix('orang-tua')->name('orang-tua.')->group(function (): void {
            Route::get('/', [OrangTuaController::class, 'kehadiran'])->name('kehadiran');
            Route::get('izin', [OrangTuaController::class, 'izin'])->name('izin.index');
            Route::post('izin', [OrangTuaController::class, 'ajukanIzin'])
                ->middleware('throttle:10,1,izin-anak')
                ->name('izin.store');
        });
    });
});
