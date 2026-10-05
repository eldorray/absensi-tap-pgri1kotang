<?php

test('tema papan absen memakai palet, font, dan radius desain A', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $vite = file_get_contents(base_path('vite.config.ts'));

    expect($css)
        ->toContain('--g-bg: #f4f1ea;')
        ->toContain('--g-blue: #24613a;')
        ->toContain('--g-bg: #121712;')
        ->toContain('--g-sky-c: #e3ebf6;')
        ->toContain("'Bricolage Grotesque', 'Public Sans'")
        ->toContain("'Public Sans'")
        ->toContain("'JetBrains Mono'")
        ->toContain('--radius-2xl: 10px;')
        ->not->toContain('Roboto')
        ->and($vite)
        ->toContain("google('Public Sans'")
        ->toContain("google('Bricolage Grotesque'")
        ->toContain("google('JetBrains Mono'")
        ->not->toContain('Roboto');
});

test('permukaan rata dan gerak pegas papan absen', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--spring: linear(')
        ->toContain('--dur: 520ms;')
        ->toContain('.press:active')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('font-family: var(--font-display);')
        // Morph sudut Material dibuang: kartu A diam saat disorot.
        ->not->toContain('border-radius: 56px 28px 56px 28px');
});

test('pegas gesture menghormati pengaturan kurangi gerak', function () {
    $pegas = file_get_contents(resource_path('js/lib/pegas.ts'));

    expect($pegas)
        ->toContain('export function jalankan(')
        ->toContain('export function proyeksi(')
        ->toContain('export function karet(')
        ->toContain('export function kurvaPegas(')
        ->toContain("'(prefers-reduced-motion: reduce)'");
});

test('sheet bawah ditarik turun hanya dari pegangannya', function () {
    $aksi = file_get_contents(resource_path('js/lib/tarikTutup.ts'));
    $konten = file_get_contents(resource_path('js/components/ui/sheet/SheetContent.svelte'));
    $profil = file_get_contents(resource_path('js/components/GuruProfileSheet.svelte'));

    expect($aksi)
        // Isi sheet yang digulir tidak boleh ikut menutup sheet.
        ->toContain("closest('[data-tarik]')")
        ->toContain('setPointerCapture')
        ->and($konten)
        ->toContain('use:tarikTutup')
        ->toContain('easing: kurvaPegas')
        ->toContain("aktif: side === 'bottom'")
        ->toContain('reduceMotion ? 0')
        ->and($profil)
        ->toContain('data-tarik');
});
