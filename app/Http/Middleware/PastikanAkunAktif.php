<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanAkunAktif
{
    /**
     * Token API berumur panjang, jadi akun yang dinonaktifkan TU harus ditolak
     * di setiap permintaan, bukan hanya saat login seperti sesi web. Tokennya
     * sekalian dicabut supaya HP itu kembali ke layar login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            $user->tokens()->delete();

            abort(401, __('auth.inactive'));
        }

        return $next($request);
    }
}
