<?php

namespace App\Actions\Absensi;

use App\Enums\StatusPerangkat;
use App\Models\Perangkat;
use App\Models\User;
use App\Support\KunciPerangkat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Padanan passkey untuk aplikasi Android.
 *
 * Server menerbitkan tantangan acak, lalu aplikasi menandatanganinya dengan
 * kunci privat di Android Keystore yang hanya terbuka setelah sidik jari atau
 * kunci layar. Tanda tangan sah atas tantangan yang masih segar membuktikan
 * pemilik HP terdaftar sendiri yang menekan tombol -- sama seperti verifikasi
 * passkey sebelum tap di web.
 */
class VerifikasiKunciPerangkat
{
    public function terbitkan(User $guru): string
    {
        $tantangan = Str::random(43);

        Cache::put($this->kunciCache($guru, $tantangan), true, CatatAbsensi::UMUR_VERIFIKASI_DETIK);

        return $tantangan;
    }

    /**
     * Tantangan dibuang begitu diperiksa, sah atau tidak, supaya satu tanda
     * tangan tidak bisa dipakai untuk dua tap.
     */
    public function sah(User $guru, string $perangkatUuid, ?string $tantangan, ?string $tandaTangan): bool
    {
        if ($tantangan === null || $tandaTangan === null) {
            return false;
        }

        if (Cache::pull($this->kunciCache($guru, $tantangan)) !== true) {
            return false;
        }

        $pem = Perangkat::query()
            ->where('user_id', $guru->id)
            ->where('uuid', $perangkatUuid)
            ->where('status', StatusPerangkat::Active)
            ->value('kunci_publik');

        return is_string($pem) && KunciPerangkat::sah($pem, $tantangan, $tandaTangan);
    }

    private function kunciCache(User $guru, string $tantangan): string
    {
        return "absensi.tantangan.{$guru->id}.".hash('sha256', $tantangan);
    }
}
