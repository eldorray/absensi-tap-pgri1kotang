<script module lang="ts">
    import { dashboard } from '@/routes/admin';
    export const layout = {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import { flip } from 'svelte/animate';
    import { Tween } from 'svelte/motion';
    import { fly, slide } from 'svelte/transition';
    import { updatePerangkat } from '@/actions/App/Http/Controllers/Admin/GuruController';
    import { update as reviewIzin } from '@/actions/App/Http/Controllers/Admin/IzinController';
    import AppHead from '@/components/AppHead.svelte';
    import KartuGeser from '@/components/KartuGeser.svelte';
    import MasukKelasPantauan from '@/components/MasukKelasPantauan.svelte';
    import type { PantauanMasukKelas } from '@/components/MasukKelasPantauan.svelte';
    import NotifikasiPush from '@/components/NotifikasiPush.svelte';
    import SegmenGeser from '@/components/SegmenGeser.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { geraknyaDikurangi, kurvaPegas } from '@/lib/pegas';
    import { toUrl } from '@/lib/utils';
    import { index as guruIndex } from '@/routes/admin/guru';
    import { index as izinIndex } from '@/routes/admin/izin';
    import { index as izinOrangTuaIndex } from '@/routes/admin/izin-orang-tua';
    import { index as orangTuaIndex } from '@/routes/admin/orang-tua';
    import { index as rekapHarianIndex } from '@/routes/admin/rekap-harian';
    import { index as siswaIndex } from '@/routes/admin/siswa';
    import { index as userIndex } from '@/routes/admin/user';

    type Log = {
        id: number;
        nama: string;
        tipe: string;
        hasil: string;
        diterima: boolean;
        waktu: string | null;
        lokasi: string | null;
        jarak_meter: number | null;
        terverifikasi: boolean;
    };

    type BarisPapan = {
        id: number;
        nama: string;
        status: string;
        label: string;
        jam_masuk: string | null;
    };

    type IzinMenunggu = {
        id: number;
        nama: string;
        tipe: string;
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
    };

    type PerangkatMenunggu = {
        id: number;
        nama: string;
        label: string;
        diajukan: string | null;
    };

    let {
        tanggal,
        papanGuru,
        menungguPersetujuan,
        perluTindakan,
        master,
        bulanIni,
        log,
        vapidPublicKey,
        masukKelas = null,
    }: {
        tanggal: string;
        papanGuru: BarisPapan[];
        menungguPersetujuan: {
            izin: IzinMenunggu[];
            perangkat: PerangkatMenunggu[];
        };
        perluTindakan: Record<string, number>;
        master: Record<string, number>;
        bulanIni: Record<string, number>;
        log: Log[];
        vapidPublicKey: string | null;
        masukKelas?: PantauanMasukKelas | null;
    } = $props();

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const tanggalPendek = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
    });

    const namaBulan = new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        year: 'numeric',
    });

    const labelHasil: Record<string, string> = {
        diterima: 'Diterima',
        luar_radius: 'Luar radius',
        akurasi_buruk: 'GPS lemah',
        perangkat_asing: 'HP asing',
        duplikat: 'Duplikat',
        passkey_invalid: 'Biometrik gagal',
        belum_masuk: 'Belum tap masuk',
        luar_jadwal: 'Luar jendela',
    };

    const labelTipeIzin: Record<string, string> = {
        izin: 'Izin',
        sakit: 'Sakit',
        cuti: 'Cuti',
    };

    /** Kelompok papan: izin, sakit, dan cuti sama-sama "berhalangan resmi". */
    function kelompok(status: string): string {
        return status === 'sakit' || status === 'cuti' ? 'izin' : status;
    }

    /** Yang perlu dihubungi di depan, yang sudah beres di belakang. */
    const urutan: Record<string, number> = {
        belum: 0,
        alfa: 1,
        terlambat: 2,
        izin: 3,
        hadir: 4,
    };

    /** Guru yang memang wajib absen hari ini. */
    const wajib = $derived(
        papanGuru.filter(
            (baris) =>
                baris.status !== 'libur' && baris.status !== 'bukan_hari_kerja',
        ),
    );
    const jumlah = (status: string): number =>
        wajib.filter((baris) => kelompok(baris.status) === status).length;
    const sudahTap = $derived(jumlah('hadir') + jumlah('terlambat'));

    const angka = new Tween(0, {
        duration: geraknyaDikurangi() ? 0 : 900,
        easing: kurvaPegas,
    });

    $effect(() => {
        angka.target = sudahTap;
    });

    /** Bilah ringkasan: lebar tiap segmen sebanding jumlahnya. */
    const segmen = $derived(
        [
            { kunci: 'hadir', label: 'Hadir', warna: 'bg-primary' },
            {
                kunci: 'terlambat',
                label: 'Terlambat',
                warna: 'bg-[var(--g-amber)]',
            },
            {
                kunci: 'izin',
                label: 'Izin / sakit / cuti',
                warna: 'bg-[var(--g-sky-ink-2)]',
            },
            { kunci: 'belum', label: 'Belum tap', warna: 'segmen-belum' },
            { kunci: 'alfa', label: 'Alfa', warna: 'bg-destructive' },
        ].map((s) => ({ ...s, jumlah: jumlah(s.kunci) })),
    );

    type Saring = 'semua' | 'belum' | 'terlambat' | 'izin' | 'hadir';
    let saring = $state<Saring>('semua');

    const opsiSaring = $derived<
        { nilai: Saring; label: string; jumlah: number }[]
    >([
        { nilai: 'semua', label: 'Semua', jumlah: wajib.length },
        { nilai: 'belum', label: 'Belum', jumlah: jumlah('belum') },
        { nilai: 'terlambat', label: 'Telat', jumlah: jumlah('terlambat') },
        { nilai: 'izin', label: 'Izin', jumlah: jumlah('izin') },
        { nilai: 'hadir', label: 'Hadir', jumlah: jumlah('hadir') },
    ]);

    const papan = $derived(
        wajib
            .filter(
                (baris) =>
                    saring === 'semua' || kelompok(baris.status) === saring,
            )
            .sort(
                (a, b) =>
                    (urutan[kelompok(a.status)] ?? 9) -
                    (urutan[kelompok(b.status)] ?? 9),
            ),
    );

    const gayaPapan: Record<string, string> = {
        belum: 'border-dashed border-[var(--g-ink-2)]/50 bg-transparent',
        alfa: 'border-[var(--g-red-line)] bg-[var(--g-red-c)]',
        terlambat: 'border-[var(--g-yellow-line)] bg-[var(--g-yellow-c)]',
        izin: 'border-[var(--g-sky-line)] bg-[var(--g-sky-c)]',
        hadir: 'border-border bg-card',
    };

    const titikPapan: Record<string, string> = {
        belum: 'bg-destructive',
        alfa: 'bg-destructive',
        terlambat: 'bg-[var(--g-amber)]',
        izin: 'bg-[var(--g-sky-ink-2)]',
        hadir: 'bg-primary',
    };

    const tekPapan: Record<string, string> = {
        belum: 'text-destructive',
        alfa: 'text-[var(--g-red-ink-2)]',
        terlambat: 'text-[var(--g-yellow-ink-2)]',
        izin: 'text-[var(--g-sky-ink-2)]',
        hadir: 'text-muted-foreground',
    };

    function keteranganPapan(baris: BarisPapan): string {
        return baris.jam_masuk ?? baris.label;
    }

    const durasi = (ms: number): number => (geraknyaDikurangi() ? 0 : ms);

    /**
     * Setiap tautan membawa filter, jadi admin langsung melihat baris yang
     * perlu ditangani -- bukan seluruh daftar lalu mencari sendiri.
     */
    const tindakan = $derived(
        [
            {
                kunci: 'izin_menunggu',
                label: 'Semua izin guru menunggu',
                href: izinIndex(),
            },
            {
                kunci: 'izin_orang_tua_menunggu',
                label: 'Izin orang tua menunggu',
                href: izinOrangTuaIndex(),
            },
            {
                kunci: 'perangkat_menunggu',
                label: 'Semua HP menunggu',
                href: guruIndex({ query: { saring: 'hp_menunggu' } }),
            },
            {
                kunci: 'guru_tanpa_kantor',
                label: 'Guru belum punya unit',
                href: userIndex({ query: { saring: 'tanpa_unit' } }),
            },
            {
                kunci: 'guru_nonaktif',
                label: 'Guru nonaktif',
                href: userIndex({ query: { saring: 'nonaktif' } }),
            },
            {
                kunci: 'siswa_tanpa_kelas',
                label: 'Siswa aktif belum masuk kelas',
                href: siswaIndex({ query: { kelas_id: 'tanpa' } }),
            },
            {
                kunci: 'orang_tua_belum_tertaut',
                label: 'Akun orang tua belum ditautkan',
                href: orangTuaIndex({ query: { penautan: 'belum' } }),
            },
        ].filter((t) => {
            const n = perluTindakan[t.kunci] ?? 0;

            // Izin dan HP yang sudah tampil sebagai kartu tidak diulang;
            // tautannya baru muncul kalau masih ada sisa di luar kartu.
            if (t.kunci === 'izin_menunggu') {
                return n > menungguPersetujuan.izin.length;
            }

            if (t.kunci === 'perangkat_menunggu') {
                return n > menungguPersetujuan.perangkat.length;
            }

            return n > 0;
        }),
    );

    const totalTindakan = $derived(
        Object.values(perluTindakan).reduce((a, b) => a + b, 0),
    );
    const adaKartu = $derived(
        menungguPersetujuan.izin.length + menungguPersetujuan.perangkat.length >
            0,
    );

    let kartuIzin = $state<Record<number, KartuGeser>>({});
    let kartuHp = $state<Record<number, KartuGeser>>({});

    function putusIzin(id: number, keputusan: 'setujui' | 'tolak'): void {
        router.patch(
            reviewIzin.url(id),
            {
                status: keputusan === 'setujui' ? 'disetujui' : 'ditolak',
                kembali: 'dashboard',
            },
            {
                preserveScroll: true,
                onError: () => kartuIzin[id]?.kembalikan(),
                onNetworkError: () => kartuIzin[id]?.kembalikan(),
            },
        );
    }

    function putusHp(id: number, keputusan: 'setujui' | 'tolak'): void {
        router.patch(
            updatePerangkat.url(id),
            {
                status: keputusan === 'setujui' ? 'active' : 'revoked',
                kembali: 'dashboard',
            },
            {
                preserveScroll: true,
                onError: () => kartuHp[id]?.kembalikan(),
                onNetworkError: () => kartuHp[id]?.kembalikan(),
            },
        );
    }

    function rentang(izin: IzinMenunggu): string {
        const mulai = tanggalPendek.format(
            new Date(`${izin.tanggal_mulai}T00:00`),
        );

        return izin.tanggal_mulai === izin.tanggal_selesai
            ? mulai
            : `${mulai} – ${tanggalPendek.format(new Date(`${izin.tanggal_selesai}T00:00`))}`;
    }
</script>

<AppHead title="Dashboard" />

<div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 sm:px-6">
    <header
        class="muncul flex flex-wrap items-end justify-between gap-4"
        style="--i: 0"
    >
        <div class="grid gap-1.5">
            <p
                class="font-mono text-xs tracking-[0.06em] text-muted-foreground uppercase"
            >
                {tanggalPanjang.format(new Date(`${tanggal}T00:00`))}
            </p>
            <h1
                class="font-display text-3xl font-bold tracking-tight sm:text-4xl"
            >
                Kehadiran hari ini
            </h1>
        </div>
        <Button
            variant="outline"
            onclick={() => router.visit(toUrl(rekapHarianIndex()))}
        >
            Rekap harian
            <ArrowRight class="size-4" aria-hidden="true" />
        </Button>
    </header>

    <section
        aria-labelledby="judul-ringkasan"
        class="muncul g-tile g-tone-plain flex-row flex-wrap items-center gap-x-10 gap-y-6"
        style="--i: 1"
    >
        {#if wajib.length === 0}
            <p class="text-muted-foreground">
                Belum ada akun guru, jadi belum ada yang bisa direkap.
            </p>
        {:else}
            <div class="grid flex-[1_1_15rem] gap-1">
                <h2
                    id="judul-ringkasan"
                    class="text-sm font-semibold text-muted-foreground"
                >
                    Sudah tap masuk
                </h2>
                <p
                    class="font-display text-7xl leading-[0.95] font-extrabold tracking-tight tabular-nums"
                >
                    {Math.round(angka.current)}<span
                        class="text-3xl font-semibold tracking-normal text-muted-foreground"
                    >
                        {` / ${wajib.length} guru`}</span
                    >
                </p>
            </div>

            <div class="grid min-w-0 flex-[3_1_26rem] gap-3.5">
                <div
                    role="img"
                    aria-label={segmen
                        .map((s) => `${s.label} ${s.jumlah}`)
                        .join(', ')}
                    class="bilah flex h-7 gap-[3px] overflow-hidden rounded-md"
                >
                    {#each segmen.filter((s) => s.jumlah > 0) as s (s.kunci)}
                        <span
                            class="block rounded-[2px] {s.warna}"
                            style="flex: {s.jumlah} 1 0;"
                        ></span>
                    {/each}
                </div>
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    {#each segmen as s (s.kunci)}
                        <li
                            class="flex items-center gap-2 {s.jumlah === 0
                                ? 'text-muted-foreground'
                                : ''}"
                        >
                            <span class="size-2.5 rounded-[2px] {s.warna}"
                            ></span>
                            {s.label}
                            <b class="font-mono">{s.jumlah}</b>
                        </li>
                    {/each}
                </ul>
                <dl
                    class="flex flex-wrap gap-x-7 gap-y-1 border-t border-border pt-3.5 text-[0.8125rem] text-muted-foreground"
                >
                    <div class="flex gap-1.5">
                        <dt>
                            {namaBulan.format(new Date(`${tanggal}T00:00`))} · hadir
                        </dt>
                        <dd class="font-mono font-semibold text-foreground">
                            {bulanIni.hadir}
                        </dd>
                    </div>
                    <div class="flex gap-1.5">
                        <dt>terlambat</dt>
                        <dd class="font-mono font-semibold text-foreground">
                            {bulanIni.terlambat}
                        </dd>
                    </div>
                    <div class="flex gap-1.5">
                        <dt>pulang cepat</dt>
                        <dd class="font-mono font-semibold text-foreground">
                            {bulanIni.pulang_cepat}
                        </dd>
                    </div>
                    <div class="flex gap-1.5">
                        <dt>belum tap pulang</dt>
                        <dd class="font-mono font-semibold text-foreground">
                            {bulanIni.belum_tap_pulang}
                        </dd>
                    </div>
                </dl>
            </div>
        {/if}
    </section>

    <div
        class="grid items-start gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
    >
        <section
            aria-labelledby="judul-papan"
            class="muncul g-tile g-tone-plain gap-4"
            style="--i: 2"
        >
            <div class="grid gap-1">
                <h2 id="judul-papan" class="font-display text-xl font-bold">
                    Papan guru
                </h2>
                <p class="text-sm text-muted-foreground">
                    Yang belum tap di depan. Hubungi sebelum absen masuk
                    ditutup.
                </p>
            </div>

            <SegmenGeser
                label="Saring papan guru"
                opsi={opsiSaring}
                bind:nilai={saring}
            />

            {#key saring}
                <ul
                    class="grid grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] gap-2"
                >
                    {#each papan as baris, i (baris.id)}
                        {@const k = kelompok(baris.status)}
                        <li
                            class="flex min-h-14 flex-col gap-1 rounded-lg border px-3 py-2.5 {gayaPapan[
                                k
                            ] ?? gayaPapan.hadir}"
                            in:fly|global={{
                                y: 8,
                                duration: durasi(460),
                                delay: durasi(Math.min(i * 16, 240)),
                                easing: kurvaPegas,
                            }}
                        >
                            <span class="truncate text-sm font-semibold"
                                >{baris.nama}</span
                            >
                            <span
                                class="flex items-center gap-1.5 font-mono text-xs {tekPapan[
                                    k
                                ] ?? tekPapan.hadir}"
                            >
                                <span
                                    class="size-1.5 shrink-0 rounded-full {titikPapan[
                                        k
                                    ] ?? titikPapan.hadir}"
                                ></span>
                                {keteranganPapan(baris)}
                            </span>
                        </li>
                    {:else}
                        <li class="col-span-full text-sm text-muted-foreground">
                            Tidak ada guru dengan status ini.
                        </li>
                    {/each}
                </ul>
            {/key}
        </section>

        <section
            aria-labelledby="judul-tindakan"
            class="muncul g-tile g-tone-plain gap-0 overflow-hidden p-0"
            style="--i: 3"
        >
            <div
                class="flex items-start justify-between gap-3 px-5 pt-5 pb-3.5"
            >
                <div class="grid gap-1">
                    <h2
                        id="judul-tindakan"
                        class="font-display text-xl font-bold"
                    >
                        Perlu tindakan
                    </h2>
                    {#if adaKartu}
                        <p class="text-xs text-muted-foreground">
                            Geser kartu: kanan setujui, kiri tolak.
                        </p>
                    {/if}
                </div>
                {#if totalTindakan > 0}
                    {#key totalTindakan}
                        <span
                            class="letup grid h-6.5 min-w-6.5 place-items-center rounded-full bg-[var(--g-amber)] px-2 text-[0.8125rem] font-bold text-[var(--g-amber-ink)]"
                            >{totalTindakan}</span
                        >
                    {/key}
                {/if}
            </div>

            {#each menungguPersetujuan.izin as izin (`izin-${izin.id}`)}
                <div
                    class="border-t border-border"
                    animate:flip={{ duration: durasi(420), easing: kurvaPegas }}
                    out:slide={{ duration: durasi(320), easing: kurvaPegas }}
                >
                    <KartuGeser
                        bind:this={kartuIzin[izin.id]}
                        onputus={(k) => putusIzin(izin.id, k)}
                    >
                        <p
                            class="text-[0.6875rem] font-semibold tracking-[0.08em] text-[var(--g-sky-ink-2)] uppercase"
                        >
                            Izin guru · {labelTipeIzin[izin.tipe] ?? izin.tipe}
                        </p>
                        <h3 class="font-bold">{izin.nama}</h3>
                        <p class="text-[0.8125rem] text-muted-foreground">
                            {rentang(izin)} · {izin.alasan}
                        </p>
                    </KartuGeser>
                </div>
            {/each}

            {#each menungguPersetujuan.perangkat as hp (`hp-${hp.id}`)}
                <div
                    class="border-t border-border"
                    animate:flip={{ duration: durasi(420), easing: kurvaPegas }}
                    out:slide={{ duration: durasi(320), easing: kurvaPegas }}
                >
                    <KartuGeser
                        bind:this={kartuHp[hp.id]}
                        labelSetujui="Setujui HP"
                        onputus={(k) => putusHp(hp.id, k)}
                    >
                        <p
                            class="text-[0.6875rem] font-semibold tracking-[0.08em] text-[var(--g-yellow-ink-2)] uppercase"
                        >
                            HP baru · {hp.label}
                        </p>
                        <h3 class="font-bold">{hp.nama}</h3>
                        <p class="text-[0.8125rem] text-muted-foreground">
                            {hp.diajukan ? `Diajukan ${hp.diajukan}. ` : ''}Tap
                            masuknya ditolak sampai HP ini disetujui.
                        </p>
                    </KartuGeser>
                </div>
            {/each}

            {#each tindakan as t (t.kunci)}
                <Link
                    href={toUrl(t.href)}
                    class="press flex min-h-13 items-center justify-between gap-3 border-t border-border px-5 text-sm hover:bg-muted/60"
                >
                    {t.label}
                    <span
                        class="inline-flex items-center gap-2 font-mono font-semibold"
                    >
                        {perluTindakan[t.kunci]}
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </span>
                </Link>
            {/each}

            {#if totalTindakan === 0}
                <p
                    class="border-t border-border px-5 py-6 text-sm text-muted-foreground"
                >
                    Tidak ada yang menunggu. Semua beres.
                </p>
            {/if}
        </section>
    </div>

    {#if masukKelas}
        <div class="muncul" style="--i: 4">
            <MasukKelasPantauan {masukKelas} />
        </div>
    {/if}

    <section
        aria-labelledby="judul-log"
        class="muncul g-tile g-tone-plain gap-3"
        style="--i: 5"
    >
        <div
            class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
        >
            <h2 id="judul-log" class="font-display text-xl font-bold">
                Log tap terbaru
            </h2>
            <p class="text-sm text-muted-foreground">
                Yang ditolak ikut tercatat: jejak percobaan titip absen.
            </p>
        </div>

        {#if log.length === 0}
            <p
                class="rounded-lg border border-dashed border-border px-4 py-6 text-center text-muted-foreground"
            >
                Belum ada tap tercatat.
            </p>
        {:else}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[38rem] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-2 py-2.5 font-semibold">Waktu</th>
                            <th class="px-2 py-2.5 font-semibold">Guru</th>
                            <th class="px-2 py-2.5 font-semibold">Tap</th>
                            <th class="px-2 py-2.5 font-semibold">Hasil</th>
                            <th class="px-2 py-2.5 font-semibold">Lokasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each log as l (l.id)}
                            <tr
                                class="border-t border-border transition-colors hover:bg-muted/50"
                            >
                                <td
                                    class="px-2 py-2.5 font-mono whitespace-nowrap"
                                    >{l.waktu ?? '-'}</td
                                >
                                <td class="px-2 py-2.5">{l.nama}</td>
                                <td class="px-2 py-2.5 capitalize">{l.tipe}</td>
                                <td class="px-2 py-2.5">
                                    <div
                                        class="flex flex-wrap items-center gap-1"
                                    >
                                        <span
                                            class="inline-flex h-6 items-center rounded-full px-2.5 text-xs font-semibold {l.diterima
                                                ? 'bg-[var(--g-green-c)] text-[var(--g-green-ink)]'
                                                : 'bg-[var(--g-red-c)] text-[var(--g-red-ink-2)]'}"
                                            >{labelHasil[l.hasil] ??
                                                l.hasil}</span
                                        >
                                        {#if l.diterima && !l.terverifikasi}
                                            <Badge
                                                variant="outline"
                                                class="gap-1"
                                            >
                                                <ShieldAlert
                                                    class="size-3"
                                                    aria-hidden="true"
                                                />
                                                Tanpa biometrik
                                            </Badge>
                                        {/if}
                                    </div>
                                </td>
                                <td
                                    class="px-2 py-2.5 whitespace-nowrap text-muted-foreground"
                                >
                                    {l.lokasi ?? '-'}{l.jarak_meter !== null
                                        ? ` · ${l.jarak_meter} m`
                                        : ''}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>

    <div class="muncul grid gap-5 lg:grid-cols-2" style="--i: 6">
        <NotifikasiPush {vapidPublicKey} />

        <section
            aria-labelledby="judul-master"
            class="g-tile g-tone-plain gap-3"
        >
            <h2 id="judul-master" class="font-display text-lg font-bold">
                Data master
            </h2>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted-foreground">Guru</dt>
                    <dd class="font-mono font-bold">{master.guru}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted-foreground">Admin</dt>
                    <dd class="font-mono font-bold">{master.admin}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted-foreground">Unit</dt>
                    <dd class="font-mono font-bold">{master.kantor}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-muted-foreground">Lokasi aktif</dt>
                    <dd class="font-mono font-bold">{master.lokasi_aktif}</dd>
                </div>
            </dl>
        </section>
    </div>
</div>

<style>
    /* Bagian halaman naik berurutan saat dibuka. */
    .muncul {
        animation: naik 0.74s var(--spring) backwards;
        animation-delay: calc(var(--i) * 60ms);
    }

    /* Bilah ringkasan terbuka dari kiri. */
    .bilah {
        animation: buka 1.1s var(--spring) 0.25s backwards;
    }

    /* Angka badge memantul saat jumlahnya berubah. */
    .letup {
        animation: letup 0.46s ease-out;
    }

    /* Dipasang lewat kelas dinamis, jadi Svelte tidak bisa melihatnya. */
    :global(.segmen-belum) {
        background: repeating-linear-gradient(
            135deg,
            var(--g-line) 0 5px,
            var(--g-surface-2) 5px 10px
        );
    }

    @keyframes naik {
        from {
            opacity: 0;
            transform: translateY(14px);
        }
    }

    @keyframes buka {
        from {
            clip-path: inset(0 100% 0 0);
        }
    }

    @keyframes letup {
        0% {
            transform: scale(0.6);
        }
        55% {
            transform: scale(1.18);
        }
    }
</style>
