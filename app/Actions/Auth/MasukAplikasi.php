<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Login aplikasi Android: tukar email + password (dan kode 2FA bila aktif)
 * dengan token Sanctum. Aturannya sama dengan login web lewat Fortify.
 */
class MasukAplikasi
{
    public function __construct(private TwoFactorAuthenticationProvider $duaFaktor) {}

    /**
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function __invoke(string $email, string $password, string $namaPerangkat, ?string $kode, ?string $kodePemulihan): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // Sama seperti FortifyServiceProvider: pesan nonaktif baru muncul setelah
        // password benar, jadi tidak membocorkan email mana yang terdaftar.
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('auth.inactive')]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $this->lolosDuaFaktor($user, $kode, $kodePemulihan);
        }

        return ['user' => $user, 'token' => $user->createToken($namaPerangkat)->plainTextToken];
    }

    /**
     * Token API tidak boleh jadi jalan pintas melewati 2FA. Tanpa kode,
     * jawabannya galat `code` -- tanda bagi aplikasi untuk meminta kode.
     *
     * @throws ValidationException
     */
    private function lolosDuaFaktor(User $user, ?string $kode, ?string $kodePemulihan): void
    {
        if ($kodePemulihan !== null) {
            $cocok = collect($user->recoveryCodes())
                ->first(fn (string $simpanan): bool => hash_equals($simpanan, $kodePemulihan));

            if (! is_string($cocok)) {
                throw ValidationException::withMessages(['recovery_code' => 'Kode pemulihan tidak valid.']);
            }

            $user->replaceRecoveryCode($cocok);

            return;
        }

        if ($kode === null) {
            throw ValidationException::withMessages(['code' => 'Masukkan kode dari aplikasi autentikator.']);
        }

        $rahasia = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        if (! $this->duaFaktor->verify($rahasia, $kode)) {
            throw ValidationException::withMessages(['code' => 'Kode autentikator tidak valid.']);
        }
    }
}
