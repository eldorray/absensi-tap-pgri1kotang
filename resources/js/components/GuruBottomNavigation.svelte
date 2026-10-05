<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import CalendarClock from 'lucide-svelte/icons/calendar-clock';
    import CalendarOff from 'lucide-svelte/icons/calendar-off';
    import ClipboardCheck from 'lucide-svelte/icons/clipboard-check';
    import MoreHorizontal from 'lucide-svelte/icons/ellipsis';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import History from 'lucide-svelte/icons/history';
    import Megaphone from 'lucide-svelte/icons/megaphone';
    import UsersRound from 'lucide-svelte/icons/users-round';
    import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
    import { pembacaBaru } from '@/lib/pengumuman';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';
    import { index as absensiSiswaIndex } from '@/routes/absensi-siswa';
    import { index as izinIndex } from '@/routes/izin';
    import { index as jadwalIndex } from '@/routes/jadwal';
    import { index as kelasSayaIndex } from '@/routes/kelas-saya';
    import { index as pengumumanIndex } from '@/routes/pengumuman';
    import { index as riwayatIndex } from '@/routes/riwayat';

    let terbuka = $state(false);

    /*
     * Riwayat di slot utama karena dibuka rutin untuk mengecek kehadiran;
     * Jadwal jarang berubah, jadi cukup di "Lainnya".
     */
    const utama = [
        { label: 'Absensi', href: dashboard(), icon: Fingerprint },
        { label: 'Izin', href: izinIndex(), icon: CalendarOff },
        { label: 'Riwayat', href: riwayatIndex(), icon: History },
    ];

    /**
     * Ada pengumuman yang belum dilihat di HP ini? Dibaca ulang setiap pindah
     * halaman, jadi titiknya hilang begitu halaman Pengumuman dibuka.
     */
    const adaPengumumanBaru = $derived.by(() => {
        void page.url;
        const terbaru = page.props.pengumumanTerbaru as
            string | null | undefined;

        return terbaru
            ? pembacaBaru()({ id: 0, judul: '', isi: '', dibuat: terbaru })
            : false;
    });

    const punyaKelas = $derived(page.props.auth.punyaKelas === true);
    const lainnyaAktif = $derived(
        isActive(toUrl(jadwalIndex())) ||
            isActive(toUrl(pengumumanIndex())) ||
            (punyaKelas &&
                (isActive(toUrl(kelasSayaIndex())) ||
                    isActive(toUrl(absensiSiswaIndex())))),
    );

    function isActive(href: string): boolean {
        const currentPath = page.url.split('?')[0];

        return currentPath === href || currentPath.startsWith(`${href}/`);
    }

    /**
     * Slot yang ditandai pil: menu utama yang sedang dibuka, atau "Lainnya"
     * saat sheet-nya terbuka atau halamannya ada di dalamnya. -1 = halaman di
     * luar navigasi (mis. Profil): pil disembunyikan, bukan menunjuk slot salah.
     */
    const indeksPil = $derived.by(() => {
        if (terbuka || lainnyaAktif) {
            return 3;
        }

        return utama.findIndex((item) => isActive(toUrl(item.href)));
    });
</script>

<Sheet bind:open={terbuka}>
    <SheetContent
        side="bottom"
        class="inset-x-0 mx-auto h-fit max-h-[80svh] w-full max-w-lg gap-0 rounded-t-[1.25rem] border-x border-t border-border bg-popover/95 p-0 backdrop-blur-xl backdrop-saturate-150"
    >
        <div data-tarik class="touch-none px-5 pt-3 pb-2">
            <div
                class="mx-auto h-1.5 w-10 rounded-full bg-muted-foreground/25"
            ></div>
            <SheetTitle class="mt-3 font-display text-xl font-bold"
                >Lainnya</SheetTitle
            >
        </div>

        <nav
            aria-label="Menu lainnya"
            class="grid gap-1 px-3 pb-3"
            style="padding-bottom: max(1rem, env(safe-area-inset-bottom));"
        >
            <Link
                href={toUrl(pengumumanIndex())}
                onclick={() => (terbuka = false)}
                class="press flex min-h-14 items-center gap-3 rounded-lg px-3 font-semibold {isActive(
                    toUrl(pengumumanIndex()),
                )
                    ? 'bg-accent text-accent-foreground'
                    : 'hover:bg-muted'}"
            >
                <span
                    class="relative grid size-10 place-items-center rounded-lg bg-muted"
                    ><Megaphone
                        class="size-5"
                        aria-hidden="true"
                    />{#if adaPengumumanBaru}<span
                            class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-[var(--g-amber)] ring-2 ring-background"
                        ></span>{/if}</span
                >
                <span
                    >Pengumuman{#if adaPengumumanBaru}<span
                            class="ml-2 rounded-full bg-[var(--g-amber)] px-2 py-0.5 text-[0.6875rem] font-bold text-[var(--g-amber-ink)]"
                            >Baru</span
                        >{/if}<span
                        class="block text-xs font-normal text-muted-foreground"
                        >Informasi dari sekolah</span
                    ></span
                >
            </Link>

            <Link
                href={toUrl(jadwalIndex())}
                onclick={() => (terbuka = false)}
                class="press flex min-h-14 items-center gap-3 rounded-lg px-3 font-semibold {isActive(
                    toUrl(jadwalIndex()),
                )
                    ? 'bg-accent text-accent-foreground'
                    : 'hover:bg-muted'}"
            >
                <span
                    class="grid size-10 place-items-center rounded-lg bg-muted"
                    ><CalendarClock class="size-5" aria-hidden="true" /></span
                >
                <span
                    >Jadwal<span
                        class="block text-xs font-normal text-muted-foreground"
                        >Jam masuk dan pulang</span
                    ></span
                >
            </Link>

            {#if punyaKelas}
                <Link
                    href={toUrl(kelasSayaIndex())}
                    onclick={() => (terbuka = false)}
                    class="press flex min-h-14 items-center gap-3 rounded-lg px-3 font-semibold {isActive(
                        toUrl(kelasSayaIndex()),
                    )
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-muted'}"
                >
                    <span
                        class="grid size-10 place-items-center rounded-lg bg-muted"
                        ><UsersRound class="size-5" aria-hidden="true" /></span
                    >
                    <span
                        >Kelas Saya<span
                            class="block text-xs font-normal text-muted-foreground"
                            >Daftar kelas dan siswa</span
                        ></span
                    >
                </Link>
                <Link
                    href={toUrl(absensiSiswaIndex())}
                    onclick={() => (terbuka = false)}
                    class="press flex min-h-14 items-center gap-3 rounded-lg px-3 font-semibold {isActive(
                        toUrl(absensiSiswaIndex()),
                    )
                        ? 'bg-accent text-accent-foreground'
                        : 'hover:bg-muted'}"
                >
                    <span
                        class="grid size-10 place-items-center rounded-lg bg-muted"
                        ><ClipboardCheck
                            class="size-5"
                            aria-hidden="true"
                        /></span
                    >
                    <span
                        >Absensi Siswa<span
                            class="block text-xs font-normal text-muted-foreground"
                            >Lihat kehadiran kelas</span
                        ></span
                    >
                </Link>
            {/if}
        </nav>
    </SheetContent>
</Sheet>

<nav
    aria-label="Navigasi utama guru"
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/90 px-2 pt-1.5 backdrop-blur-xl backdrop-saturate-150"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));"
>
    <div class="relative mx-auto grid max-w-lg grid-cols-4">
        <!-- Satu pil yang meluncur antar-slot, bukan latar per tombol. -->
        <span
            aria-hidden="true"
            class="pointer-events-none absolute top-1 left-0 flex h-8 w-1/4 justify-center transition-[transform,opacity] duration-(--dur) ease-(--spring) {indeksPil <
            0
                ? 'opacity-0'
                : ''}"
            style="transform: translateX({Math.max(indeksPil, 0) * 100}%);"
        >
            <span class="h-8 w-14 rounded-full bg-accent"></span>
        </span>

        {#each utama as item, indeks (item.label)}
            {@const href = toUrl(item.href)}
            {@const ditandai = indeksPil === indeks}
            <Link
                {href}
                class="press relative flex min-h-14 flex-col items-center gap-1 pt-1 text-[0.6875rem] font-semibold {ditandai
                    ? 'text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'}"
                aria-current={isActive(href) ? 'page' : undefined}
            >
                <span class="grid h-8 w-14 place-items-center">
                    <item.icon
                        class="size-5"
                        strokeWidth={ditandai ? 2.5 : 2}
                        aria-hidden="true"
                    />
                </span>
                <span>{item.label}</span>
            </Link>
        {/each}

        <button
            type="button"
            aria-label={adaPengumumanBaru
                ? 'Buka menu lainnya, ada pengumuman baru'
                : 'Buka menu lainnya'}
            aria-expanded={terbuka}
            aria-haspopup="dialog"
            onclick={() => (terbuka = true)}
            class="press relative flex min-h-14 flex-col items-center gap-1 pt-1 text-[0.6875rem] font-semibold {indeksPil ===
            3
                ? 'text-accent-foreground'
                : 'text-muted-foreground hover:text-foreground'}"
        >
            <span class="relative grid h-8 w-14 place-items-center">
                <MoreHorizontal
                    class="size-5"
                    strokeWidth={indeksPil === 3 ? 2.5 : 2}
                    aria-hidden="true"
                />
                {#if adaPengumumanBaru}
                    <span
                        class="absolute top-0.5 right-3 size-2.5 rounded-full bg-[var(--g-amber)] ring-2 ring-background"
                    ></span>
                {/if}
            </span>
            <span>Lainnya</span>
        </button>
    </div>
</nav>
