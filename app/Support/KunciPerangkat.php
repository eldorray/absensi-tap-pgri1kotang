<?php

namespace App\Support;

/**
 * Kunci publik HP Android dan pemeriksaan tanda tangannya.
 *
 * Aplikasi mengirim kunci EC P-256 sebagai SubjectPublicKeyInfo DER yang
 * di-base64 -- persis keluaran PublicKey::getEncoded() di Android -- dan
 * tanda tangan ECDSA SHA-256 berformat DER dari Signature "SHA256withECDSA".
 */
class KunciPerangkat
{
    /**
     * Kunci dalam bentuk PEM, atau null kalau isinya bukan kunci publik EC P-256.
     */
    public static function pem(string $base64): ?string
    {
        $der = base64_decode($base64, true);

        if ($der === false || $der === '') {
            return null;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
        $kunci = openssl_pkey_get_public($pem);

        if ($kunci === false) {
            return null;
        }

        $rincian = openssl_pkey_get_details($kunci);

        if ($rincian === false || $rincian['type'] !== OPENSSL_KEYTYPE_EC || ($rincian['ec']['curve_name'] ?? null) !== 'prime256v1') {
            return null;
        }

        return $pem;
    }

    public static function sah(string $pem, string $data, string $tandaTanganBase64): bool
    {
        $tandaTangan = base64_decode($tandaTanganBase64, true);

        if ($tandaTangan === false || $tandaTangan === '') {
            return false;
        }

        return openssl_verify($data, $tandaTangan, $pem, OPENSSL_ALGO_SHA256) === 1;
    }
}
