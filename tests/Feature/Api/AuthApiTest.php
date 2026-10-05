<?php

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @return array<string, string>
 */
function payloadMasukApi(User $user, array $tambahan = []): array
{
    return ['email' => $user->email, 'password' => 'password', 'nama_perangkat' => 'Pixel 8a', ...$tambahan];
}

test('login aplikasi memberi token dan profil tanpa data rahasia', function () {
    $guru = User::factory()->create();

    $this->postJson(route('api.login'), payloadMasukApi($guru))
        ->assertOk()
        ->assertJsonPath('user.id', $guru->id)
        ->assertJsonPath('user.role', 'guru')
        ->assertJsonMissingPath('user.password')
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role', 'role_label']]);

    expect(PersonalAccessToken::query()->where('tokenable_id', $guru->id)->value('name'))->toBe('Pixel 8a');
});

test('password salah dan akun nonaktif tidak mendapat token', function () {
    $guru = User::factory()->create();
    $nonaktif = User::factory()->create(['is_active' => false]);

    $this->postJson(route('api.login'), payloadMasukApi($guru, ['password' => 'salah']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->postJson(route('api.login'), payloadMasukApi($nonaktif))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.inactive')]);

    expect(PersonalAccessToken::count())->toBe(0);
});

test('akun dengan 2FA wajib menyertakan kode sebelum mendapat token', function () {
    $guru = User::factory()->withTwoFactor()->create();

    $this->postJson(route('api.login'), payloadMasukApi($guru))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->mock(TwoFactorAuthenticationProvider::class)
        ->shouldReceive('verify')->once()->with('secret', '123456')->andReturn(true);

    $this->postJson(route('api.login'), payloadMasukApi($guru, ['code' => '123456']))
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('kode pemulihan 2FA hanya bisa dipakai sekali', function () {
    $guru = User::factory()->withTwoFactor()->create();

    $this->postJson(route('api.login'), payloadMasukApi($guru, ['recovery_code' => 'recovery-code-1']))
        ->assertOk();

    $this->postJson(route('api.login'), payloadMasukApi($guru, ['recovery_code' => 'recovery-code-1']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recovery_code');
});

test('login dibatasi lima percobaan per menit dan dijawab JSON', function () {
    $guru = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->postJson(route('api.login'), payloadMasukApi($guru, ['password' => 'salah']))->assertUnprocessable();
    }

    $this->postJson(route('api.login'), payloadMasukApi($guru))->assertTooManyRequests();
});

test('logout mencabut token HP itu saja', function () {
    $guru = User::factory()->create();
    $tokenHpIni = $guru->createToken('HP ini')->plainTextToken;
    $tokenHpLain = $guru->createToken('HP lain')->plainTextToken;

    $this->withToken($tokenHpIni)->postJson(route('api.logout'))->assertNoContent();
    $this->app['auth']->forgetGuards();

    $this->withToken($tokenHpIni)->getJson(route('api.me'))->assertUnauthorized();
    $this->app['auth']->forgetGuards();
    $this->withToken($tokenHpLain)->getJson(route('api.me'))->assertOk()->assertJsonPath('user.id', $guru->id);
});

test('akun yang dinonaktifkan setelah login ditolak dan tokennya dicabut', function () {
    $guru = User::factory()->create();
    $token = $guru->createToken('HP')->plainTextToken;
    $guru->forceFill(['is_active' => false])->save();

    $this->withToken($token)->getJson(route('api.me'))->assertUnauthorized();

    expect($guru->tokens()->count())->toBe(0);
});

test('tamu tanpa token ditolak', function () {
    $this->getJson(route('api.me'))->assertUnauthorized();
});
