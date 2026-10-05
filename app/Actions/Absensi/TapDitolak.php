<?php

namespace App\Actions\Absensi;

use App\Enums\HasilTap;
use Illuminate\Validation\ValidationException;

/**
 * Penolakan tap yang membawa kodenya.
 *
 * Aplikasi Android memilih ikon dan langkah berikutnya dari kode ini, bukan
 * dengan menebak dari kalimat pesan. Bagi web ini tetap ValidationException
 * biasa dengan galat `tap`.
 */
class TapDitolak extends ValidationException
{
    public HasilTap $hasil;

    public static function karena(HasilTap $hasil, string $pesan): self
    {
        $galat = static::withMessages(['tap' => $pesan]);
        $galat->hasil = $hasil;

        return $galat;
    }
}
