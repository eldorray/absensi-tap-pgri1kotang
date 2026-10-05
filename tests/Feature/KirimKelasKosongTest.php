<?php

use App\Models\AbsensiKelas;
use App\Models\JadwalKerja;
use App\Models\Kantor;
use App\Models\Kelas;
use App\Models\User;
use App\Notifications\PushAdmin;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Carbon::setTestNow('2026-09-07 06:51:00'); // Senin, semenit lewat batas
    $this->seed(JadwalKerjaSeeder::class);
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => '06:50:00']);
    $this->admin = User::factory()->admin()->create();
    $this->admin->updatePushSubscription('https://fcm.googleapis.com/fcm/send/a');

    $kantor = Kantor::factory()->create();
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '7A', 'tingkat' => 7]);
    Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '8B', 'tingkat' => 8]);
    $this->terisi = Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => '9C', 'tingkat' => 9]);
    AbsensiKelas::factory()->create(['kelas_id' => $this->terisi->id]);
});

test('lewat batas, admin menerima daftar kelas kosong sekali saja', function () {
    $this->artisan('masuk-kelas:kirim-kelas-kosong')->assertSuccessful();
    Carbon::setTestNow('2026-09-07 06:52:00');
    $this->artisan('masuk-kelas:kirim-kelas-kosong')->assertSuccessful();

    Notification::assertSentToTimes($this->admin, PushAdmin::class, 1);
    Notification::assertSentTo(
        $this->admin,
        PushAdmin::class,
        fn (PushAdmin $push): bool => $push->judul === '2 kelas belum ada guru'
            && $push->isi === '7A, 8B'
            && $push->url === route('admin.dashboard'),
    );
});

test('belum lewat batas tidak ada push', function () {
    Carbon::setTestNow('2026-09-07 06:50:59');

    $this->artisan('masuk-kelas:kirim-kelas-kosong')->assertSuccessful();

    Notification::assertNothingSent();
});

test('lebih dari 30 menit sesudah batas push dianggap basi', function () {
    Carbon::setTestNow('2026-09-07 07:21:00');

    $this->artisan('masuk-kelas:kirim-kelas-kosong')->assertSuccessful();

    Notification::assertNothingSent();
});

test('semua kelas terisi tidak ada push', function () {
    Kelas::query()->where('id', '!=', $this->terisi->id)->update(['is_active' => false]);

    $this->artisan('masuk-kelas:kirim-kelas-kosong')->assertSuccessful();

    Notification::assertNothingSent();
});

test('hari tanpa absen masuk kelas tidak ada push', function () {
    JadwalKerja::query()->whereNull('user_id')->update(['jam_masuk_kelas' => null]);

    $this->artisan('masuk-kelas:kirim-kelas-kosong')->assertSuccessful();

    Notification::assertNothingSent();
});

test('lebih dari sepuluh kelas kosong diringkas', function () {
    $kantor = Kantor::factory()->create();
    foreach (range(1, 10) as $nomor) {
        Kelas::factory()->create(['kantor_id' => $kantor->id, 'nama' => 'X'.$nomor, 'tingkat' => 10]);
    }

    $this->artisan('masuk-kelas:kirim-kelas-kosong')->assertSuccessful();

    Notification::assertSentTo(
        $this->admin,
        PushAdmin::class,
        fn (PushAdmin $push): bool => $push->judul === '12 kelas belum ada guru' && str_ends_with($push->isi, ' +2 lainnya'),
    );
});

test('scheduler memanggil pengecekan kelas kosong', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('masuk-kelas:kirim-kelas-kosong')
        ->assertSuccessful();
});
