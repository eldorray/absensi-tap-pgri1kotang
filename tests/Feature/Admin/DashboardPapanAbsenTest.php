<?php

use App\Enums\StatusIzin;
use App\Enums\StatusPerangkat;
use App\Models\Izin;
use App\Models\Perangkat;
use App\Models\User;
use Database\Seeders\JadwalKerjaSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-07 07:30:00');
    $this->seed(JadwalKerjaSeeder::class);
});

test('dashboard mengirim papan guru dengan status hari ini', function () {
    User::factory()->create(['name' => 'Sri Rahayu']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p
            ->has('papanGuru', 1)
            ->where('papanGuru.0.nama', 'Sri Rahayu')
            ->where('papanGuru.0.status', 'belum')
            ->where('papanGuru.0.jam_masuk', null)
            ->has('papanGuru.0.id')
            ->has('papanGuru.0.label'));
});

test('dashboard mengirim izin dan HP yang menunggu untuk diputuskan langsung', function () {
    $guru = User::factory()->create(['name' => 'Dra. Hartini']);
    Izin::factory()->for($guru)->create(['tipe' => 'sakit', 'alasan' => 'Demam.']);
    Izin::factory()->for($guru)->disetujui()->create();
    Perangkat::factory()->for(User::factory()->create(['name' => 'Rina Marlina']))->create(['status' => StatusPerangkat::Pending, 'label' => 'Samsung A15']);
    // HP admin tidak bisa disetujui dari halaman Guru, jadi tidak ikut.
    Perangkat::factory()->for(User::factory()->admin()->create())->create(['status' => StatusPerangkat::Pending]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p
            ->has('menungguPersetujuan.izin', 1)
            ->where('menungguPersetujuan.izin.0.nama', 'Dra. Hartini')
            ->where('menungguPersetujuan.izin.0.tipe', 'sakit')
            ->where('menungguPersetujuan.izin.0.alasan', 'Demam.')
            ->where('menungguPersetujuan.izin.0.tanggal_mulai', '2026-09-07')
            ->has('menungguPersetujuan.perangkat', 1)
            ->where('menungguPersetujuan.perangkat.0.nama', 'Rina Marlina')
            ->where('menungguPersetujuan.perangkat.0.label', 'Samsung A15'));
});

test('memutuskan izin dari dashboard kembali ke dashboard', function () {
    $izin = Izin::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.izin.update', $izin), ['status' => 'disetujui', 'kembali' => 'dashboard'])
        ->assertRedirect(route('admin.dashboard'));

    expect($izin->fresh()->status)->toBe(StatusIzin::Disetujui);
});

test('memutuskan HP dari dashboard kembali ke dashboard', function () {
    $perangkat = Perangkat::factory()->create(['status' => StatusPerangkat::Pending]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.perangkat.update', $perangkat), ['status' => 'active', 'kembali' => 'dashboard'])
        ->assertRedirect(route('admin.dashboard'));

    expect($perangkat->fresh()->status)->toBe(StatusPerangkat::Active);
});

test('tujuan kembali hanya boleh dashboard', function () {
    $izin = Izin::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.izin.update', $izin), ['status' => 'disetujui', 'kembali' => 'https://contoh.test'])
        ->assertSessionHasErrors('kembali');

    expect($izin->fresh()->status)->toBe(StatusIzin::Pending);
});

test('halaman dashboard admin memakai papan, kartu geser, dan segmen saring', function () {
    $halaman = file_get_contents(resource_path('js/pages/admin/Dashboard.svelte'));

    expect($halaman)
        ->toContain('<SegmenGeser')
        ->toContain('<KartuGeser')
        // Keputusan dari dashboard kembali ke dashboard, bukan ke halaman Izin.
        ->toContain("kembali: 'dashboard'")
        // Kartu yang gagal dikirim kembali ke tempatnya.
        ->toContain('kembalikan()')
        ->toContain('{#key saring}');
});

test('kartu izin dashboard menandai lampiran supaya bisa dicek sebelum disetujui', function () {
    Izin::factory()->create(['lampiran_path' => 'izin/surat.pdf']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($p) => $p->where('menungguPersetujuan.izin.0.ada_lampiran', true));

    expect(file_get_contents(resource_path('js/pages/admin/Dashboard.svelte')))
        ->toContain('lampiran.url(izin.id)')
        ->toContain('line-clamp-2');
});
