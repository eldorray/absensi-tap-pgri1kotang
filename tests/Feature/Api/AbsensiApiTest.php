<?php

use App\Enums\HasilTap;
use App\Enums\StatusPerangkat;
use App\Models\AbsensiAttempt;
use App\Models\Perangkat;
use App\Models\User;
use App\Support\KunciPerangkat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 07:00:00');
});

/**
 * Pasangan kunci EC P-256 seperti yang dibuat Android Keystore.
 *
 * @return array{privat: OpenSSLAsymmetricKey, publik: string}
 */
function kunciPerangkatUji(): array
{
    $privat = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $pem = openssl_pkey_get_details($privat)['key'];

    // Base64 DER tanpa kepala PEM: persis PublicKey::getEncoded() di Android.
    return ['privat' => $privat, 'publik' => str_replace(["\n", '-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'], '', $pem)];
}

function tandaTanganiUji(OpenSSLAsymmetricKey $privat, string $tantangan): string
{
    openssl_sign($tantangan, $tandaTangan, $privat, OPENSSL_ALGO_SHA256);

    return base64_encode($tandaTangan);
}

/**
 * Guru siap tap dengan HP berkunci perangkat.
 *
 * @return array{0: User, 1: Perangkat, 2: OpenSSLAsymmetricKey}
 */
function guruAplikasiSiapTap(): array
{
    [$guru, $perangkat] = guruSiapAbsen();
    $kunci = kunciPerangkatUji();
    $perangkat->forceFill(['kunci_publik' => KunciPerangkat::pem($kunci['publik'])])->save();

    return [$guru, $perangkat, $kunci['privat']];
}

/**
 * @return array<string, mixed>
 */
function payloadTapApi(Perangkat $perangkat, string $tipe = 'masuk', array $tambahan = []): array
{
    return [
        'tipe' => $tipe,
        'latitude' => -6.1753924,
        'longitude' => 106.8271528,
        'accuracy' => 12,
        'device_uuid' => $perangkat->uuid,
        ...$tambahan,
    ];
}

test('beranda API memuat jadwal dan status HP yang dikirim', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    Sanctum::actingAs($guru);

    $this->getJson(route('api.beranda', ['device_uuid' => $perangkat->uuid]))
        ->assertOk()
        ->assertJsonPath('jadwal.jam_masuk', '07:00')
        ->assertJsonPath('hariIni', null)
        ->assertJsonPath('statusPerangkat', 'active')
        ->assertJsonPath('lokasis.0.nama', 'Gerbang Utama')
        ->assertJsonStructure(['waktuServer', 'pengumumans', 'punyaPasskey']);
});

test('wilayah pegawai tertutup bagi orang tua dan tamu', function () {
    $this->getJson(route('api.beranda'))->assertUnauthorized();

    Sanctum::actingAs(User::factory()->orangTua()->create());

    $this->getJson(route('api.beranda'))->assertForbidden();
    $this->postJson(route('api.absensi.store'), [])->assertForbidden();
});

test('HP baru didaftarkan aktif bersama kunci publiknya', function () {
    $guru = User::factory()->create();
    $uuid = (string) Str::uuid();
    Sanctum::actingAs($guru);

    $this->postJson(route('api.perangkat.store'), ['device_uuid' => $uuid, 'kunci_publik' => kunciPerangkatUji()['publik']])
        ->assertCreated()
        ->assertJsonPath('perangkat.status', 'active')
        ->assertJsonPath('perangkat.punya_kunci', true);

    expect(Perangkat::where('uuid', $uuid)->value('kunci_publik'))->toStartWith('-----BEGIN PUBLIC KEY-----');
});

test('kunci publik yang bukan EC P-256 ditolak', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(route('api.perangkat.store'), ['device_uuid' => (string) Str::uuid(), 'kunci_publik' => base64_encode('bukan kunci')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kunci_publik');
});

test('HP yang sudah terdaftar tidak menerima kunci pengganti', function () {
    [$guru, $perangkat] = guruAplikasiSiapTap();
    $kunciLama = $perangkat->kunci_publik;
    Sanctum::actingAs($guru);

    $this->postJson(route('api.perangkat.store'), ['device_uuid' => $perangkat->uuid, 'kunci_publik' => kunciPerangkatUji()['publik']])
        ->assertOk();

    expect($perangkat->fresh()->kunci_publik)->toBe($kunciLama);
});

test('HP kedua menunggu persetujuan TU', function () {
    [$guru] = guruSiapAbsen();
    Sanctum::actingAs($guru);

    $this->postJson(route('api.perangkat.store'), ['device_uuid' => (string) Str::uuid(), 'kunci_publik' => kunciPerangkatUji()['publik']])
        ->assertCreated()
        ->assertJsonPath('perangkat.status', StatusPerangkat::Pending->value);
});

test('tap bertanda tangan kunci perangkat tercatat terverifikasi', function () {
    [$guru, $perangkat, $privat] = guruAplikasiSiapTap();
    pasangPasskeyPalsu($guru);
    Sanctum::actingAs($guru);

    $tantangan = $this->getJson(route('api.absensi.tantangan'))->assertOk()->json('tantangan');

    $this->postJson(route('api.absensi.store'), payloadTapApi($perangkat, tambahan: [
        'tantangan' => $tantangan,
        'tanda_tangan' => tandaTanganiUji($privat, $tantangan),
    ]))
        ->assertOk()
        ->assertJsonPath('absensi.status', 'hadir')
        ->assertJsonPath('absensi.jam_masuk', '07:00')
        ->assertJsonPath('absensi.terverifikasi', true)
        ->assertJsonPath('pesan', 'Absen masuk tercatat pukul 07.00. Tepat waktu.');
});

test('tantangan hanya berlaku untuk satu tap', function () {
    [$guru, $perangkat, $privat] = guruAplikasiSiapTap();
    pasangPasskeyPalsu($guru);
    Sanctum::actingAs($guru);

    $tantangan = $this->getJson(route('api.absensi.tantangan'))->json('tantangan');
    $tambahan = ['tantangan' => $tantangan, 'tanda_tangan' => tandaTanganiUji($privat, $tantangan)];

    $this->postJson(route('api.absensi.store'), payloadTapApi($perangkat, tambahan: $tambahan))->assertOk();

    Carbon::setTestNow('2026-09-07 14:00:00');

    $this->postJson(route('api.absensi.store'), payloadTapApi($perangkat, 'pulang', $tambahan))
        ->assertUnprocessable()
        ->assertJsonPath('kode', HasilTap::PasskeyInvalid->value);
});

test('tanda tangan dari kunci lain tidak dianggap verifikasi', function () {
    [$guru, $perangkat] = guruAplikasiSiapTap();
    pasangPasskeyPalsu($guru);
    Sanctum::actingAs($guru);

    $tantangan = $this->getJson(route('api.absensi.tantangan'))->json('tantangan');

    $this->postJson(route('api.absensi.store'), payloadTapApi($perangkat, tambahan: [
        'tantangan' => $tantangan,
        'tanda_tangan' => tandaTanganiUji(kunciPerangkatUji()['privat'], $tantangan),
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('kode', HasilTap::PasskeyInvalid->value)
        ->assertJsonValidationErrors(['tap' => 'Verifikasi sidik jari dulu, lalu tap lagi.']);
});

test('guru tanpa passkey tetap bisa tap tanpa tanda tangan, tercatat tanpa biometrik', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    Sanctum::actingAs($guru);

    $this->postJson(route('api.absensi.store'), payloadTapApi($perangkat))
        ->assertOk()
        ->assertJsonPath('absensi.terverifikasi', false);
});

test('penolakan tap membawa kode dan pesan yang sama dengan web', function () {
    [$guru, $perangkat] = guruSiapAbsen();
    Sanctum::actingAs($guru);

    $this->postJson(route('api.absensi.store'), payloadTapApi($perangkat, tambahan: ['latitude' => -6.1723]))
        ->assertUnprocessable()
        ->assertJsonPath('kode', HasilTap::LuarRadius->value)
        ->assertJsonValidationErrors('tap');

    expect(AbsensiAttempt::where('hasil', HasilTap::LuarRadius)->count())->toBe(1);
});
