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

test('sidebar admin gelap dengan badge kuning', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $navMain = file_get_contents(resource_path('js/components/NavMain.svelte'));
    $tombol = file_get_contents(resource_path('js/components/ui/sidebar/SidebarMenuButton.svelte'));

    expect($css)
        ->toContain('--sidebar-background: #18201b;')
        ->and($navMain)
        ->toContain('bg-[var(--g-amber)]')
        ->toContain('text-[var(--g-amber-ink)]')
        ->and($tombol)
        ->not->toContain('rounded-full p-2');
});

test('nav bawah memakai pil geser dan sheet lainnya', function () {
    $guru = file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte'));
    $ortu = file_get_contents(resource_path('js/components/OrangTuaBottomNavigation.svelte'));

    expect($guru)
        ->toContain('const indeksPil = $derived')
        // Di halaman menu Lainnya pil pindah ke slot ke-4.
        ->toContain('terbuka || lainnyaAktif || menujuLainnya) {')
        ->toContain('<SheetContent')
        ->toContain('side="bottom"')
        ->toContain('data-tarik')
        ->toContain('translateX(')
        // Halaman di luar navigasi: pil disembunyikan.
        ->toContain('opacity-0')
        ->and($ortu)
        ->toContain('translateX(');
});

test('jempol scrollbar tidak bergaris warna latar di sidebar gelap', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('background-clip: padding-box;')
        // Scrollbar overlay bawaan browser mengabaikan ::-webkit-scrollbar;
        // tanpa warna standar ia tampil putih di sidebar gelap.
        ->toContain("[data-sidebar='content'] {")
        ->toContain('scrollbar-color: #3a4a3e transparent;')
        ->not->toContain('border: 3px solid var(--g-bg);');
});

test('ketukan saat sheet memantul tidak membekukannya di tengah jalan', function () {
    $aksi = file_get_contents(resource_path('js/lib/tarikTutup.ts'));
    $turun = substr($aksi, strpos($aksi, 'const turun'), strpos($aksi, 'const gerak') - strpos($aksi, 'const turun'));

    expect($turun)
        // Pegas baru dihentikan saat tarikan benar-benar dimulai, dan jari
        // kedua tidak menimpa tarikan yang sedang berjalan.
        ->not->toContain('hentikan(p)')
        ->toContain('if (tarikan) {')
        ->and($aksi)
        ->toContain("tarikan.aktif = true;\n            hentikan(p);");
});

test('kecepatan lepas diukur saat jari diangkat, bukan dari gerakan terakhir', function () {
    $aksi = file_get_contents(resource_path('js/lib/tarikTutup.ts'));
    $naik = substr($aksi, strpos($aksi, 'const naik'));

    expect($naik)->toContain('selesai.riwayat.push({ x: p.x, t: performance.now() });');
});

test('pil tetap di slot lainnya selama pindah halaman dari sheet', function () {
    $guru = file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte'));

    expect($guru)
        ->toContain('let menujuLainnya = $state(false);')
        ->toContain('terbuka || lainnyaAktif || menujuLainnya')
        ->toContain('onFinish={() => (menujuLainnya = false)}');
});

test('izin sakit cuti memakai biru langit, bukan hijau hadir', function () {
    foreach (['js/pages/admin/RekapHarian.svelte', 'js/pages/riwayat/Index.svelte', 'js/pages/admin/Rekap.svelte', 'js/lib/pengumuman.ts'] as $file) {
        expect(file_get_contents(resource_path($file)))
            ->toContain('--g-sky-')
            ->not->toContain('--g-blue-c)');
    }
});

test('teks versi dan fokus terbaca di sidebar gelap', function () {
    expect(file_get_contents(resource_path('js/components/AppSidebar.svelte')))
        ->toContain('<AppVersion gelap />')
        ->and(file_get_contents(resource_path('js/components/AppVersion.svelte')))
        ->toContain('text-sidebar-foreground/70')
        ->and(file_get_contents(resource_path('css/app.css')))
        ->toContain('[data-sidebar] :focus-visible');
});

test('sheet diumumkan sebagai dialog berjudul', function () {
    expect(file_get_contents(resource_path('js/components/ui/sheet/SheetContent.svelte')))
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-labelledby={titleId}')
        ->and(file_get_contents(resource_path('js/components/ui/sheet/SheetTitle.svelte')))
        ->toContain('id={context?.titleId}')
        ->and(file_get_contents(resource_path('js/components/GuruBottomNavigation.svelte')))
        ->toContain('aria-current={isActive(toUrl(jadwalIndex()))');
});

test('kartu hero tanpa arti status tidak ikut biru izin', function () {
    expect(file_get_contents(resource_path('js/pages/kelas-saya/Index.svelte')))
        ->not->toContain('g-tone-blue')
        ->and(file_get_contents(resource_path('js/pages/orang-tua/Index.svelte')))
        ->not->toContain('g-tone-blue');
});

test('segmen geser punya thumb meluncur yang bisa diseret', function () {
    $segmen = file_get_contents(resource_path('js/components/SegmenGeser.svelte'));

    expect($segmen)
        ->toContain('role="group"')
        ->toContain('aria-pressed={o.nilai === nilai}')
        ->toContain('translateX(')
        // Diseret hanya kalau mulai dari segmen terpilih, seperti iOS.
        ->toContain('setPointerCapture');
});

test('kartu geser memutuskan lewat geser atau tombol', function () {
    $kartu = file_get_contents(resource_path('js/components/KartuGeser.svelte'));

    expect($kartu)
        ->toContain('proyeksi(')
        ->toContain('karet(')
        ->toContain('export function kembalikan')
        // Tombol di dalam kartu tetap tombol: geser tidak dimulai dari sana.
        ->toContain("closest('button')")
        ->toContain("lempar('setujui')")
        ->toContain("lempar('tolak')");
});

test('garis jendela absen dihitung dari jadwal yang sama dengan server', function () {
    $lib = file_get_contents(resource_path('js/lib/jendela-absen.ts'));

    expect($lib)
        ->toContain('export function segmenJendela(')
        // Batas tepat waktu = jam masuk + toleransi, sama dengan CatatAbsensi.
        ->toContain('menit(jadwal.jam_masuk) + toleransi')
        ->toContain('mnt lagi batas tepat')
        // Guru yang belum tap masuk tidak boleh diberi tahu "absen pulang dibuka".
        ->toContain('Absen masuk ditutup')
        ->toContain('sudahMasuk: boolean');
});

test('beranda guru memakai garis jendela, tombol tap lebar, dan daftar status', function () {
    $beranda = file_get_contents(resource_path('js/pages/Dashboard.svelte'));
    $tombol = file_get_contents(resource_path('js/components/TapButton.svelte'));
    $kelas = file_get_contents(resource_path('js/components/MasukKelasKartu.svelte'));

    expect($beranda)
        ->toContain('segmenJendela(')
        ->toContain('Jendela absen')
        ->toContain('Status hari ini')
        // Setelah tap masuk dan sebelum jam pulang: tanda tercatat, bukan tombol mati.
        ->toContain('Masuk tercatat')
        ->toContain('aria-label="Posisi pengumuman"')
        ->and($tombol)
        ->not->toContain('linear-gradient')
        ->toContain('class="tap-ikon"')
        ->and($kelas)
        // Kartu masuk kelas jadi tombol aksi; statusnya di daftar status.
        ->not->toContain('<section class="g-tile');
});
