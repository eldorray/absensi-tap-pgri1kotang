<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\MasukAplikasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MasukAplikasiRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function login(MasukAplikasiRequest $request, MasukAplikasi $masuk): JsonResponse
    {
        $hasil = $masuk(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('nama_perangkat')->toString(),
            $request->filled('code') ? $request->string('code')->toString() : null,
            $request->filled('recovery_code') ? $request->string('recovery_code')->toString() : null,
        );

        return response()->json(['token' => $hasil['token'], 'user' => self::profil($hasil['user'])]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => self::profil($request->user())]);
    }

    /**
     * Cabut token HP ini saja; HP lain milik akun yang sama tetap masuk.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Hanya yang dibutuhkan aplikasi; hash password dan rahasia 2FA tidak pernah ikut.
     *
     * @return array{id: int, name: string, email: string, role: string, role_label: string}
     */
    private static function profil(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
        ];
    }
}
