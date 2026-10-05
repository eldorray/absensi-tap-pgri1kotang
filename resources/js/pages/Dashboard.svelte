<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [{ title: 'Absensi', href: dashboard() }],
    };

    /**
     * Uuid yang sudah dikirim ke server sepanjang tab ini hidup.
     *
     * Pendaftarannya membalas redirect ke dashboard, jadi tanpa penjaga ini
     * halaman mount ulang, mengirim lagi, dan berputar tanpa henti.
     */
    let uuidTerkirim: string | null = null;
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import Megaphone from 'lucide-svelte/icons/megaphone';
    import ShieldAlert from 'lucide-svelte/icons/shield-alert';
    import { store as daftarkanPerangkat } from '@/actions/App/Http/Controllers/PerangkatController';
    import AppHead from '@/components/AppHead.svelte';
    import InstallPrompt from '@/components/InstallPrompt.svelte';
    import JarakLokasi from '@/components/JarakLokasi.svelte';
    import MasukKelasKartu from '@/components/MasukKelasKartu.svelte';
    import type { MasukKelas } from '@/components/MasukKelasKartu.svelte';
    import PengumumanDetail from '@/components/PengumumanDetail.svelte';
    import PengumumanKartu from '@/components/PengumumanKartu.svelte';
    import TapButton from '@/components/TapButton.svelte';
    import { Badge } from '@/components/ui/badge';
    import {
        jamServer,
        segmenJendela,
        statusJendela,
    } from '@/lib/jendela-absen';
    import { pembacaBaru } from '@/lib/pengumuman';
    import type { Pengumuman } from '@/lib/pengumuman';
    import {
        bacaDeviceUuid,
        buatDeviceUuid,
        simpanDeviceUuid,
    } from '@/lib/perangkat';
    import { toUrl } from '@/lib/utils';
    import { index as izinIndex } from '@/routes/izin';
    import { index as pengumumanIndex } from '@/routes/pengumuman';

    type Jadwal = {
        jam_masuk: string;
        jam_pulang: string;
        toleransi_menit: number;
        buka_masuk: string;
        tutup_masuk: string;
        buka_pulang: string;
        is_hari_kerja: boolean;
    };

    type Hari = {
        status: string | null;
        jam_masuk: string | null;
        jam_pulang: string | null;
        pulang_cepat: boolean;
        terverifikasi: boolean;
    };

    type Lokasi = {
        id: number;
        nama: string;
        latitude: number;
        longitude: number;
        radius_meter: number;
    };

    let {
        jadwal,
        hariIni,
        pengumumans,
        lokasis,
        punyaPasskey,
        perangkatUuidTersimpan,
        statusPerangkat = null,
        waktuServer,
        libur = null,
        masukKelas = null,
    }: {
        jadwal: Jadwal | null;
        hariIni: Hari | null;
        pengumumans: Pengumuman[];
        lokasis: Lokasi[];
        punyaPasskey: boolean;
        perangkatUuidTersimpan: string | null;
        statusPerangkat?: 'pending' | 'active' | 'revoked' | null;
        waktuServer: string;
        libur?: string | null;
        masukKelas?: MasukKelas | null;
    } = $props();

    // Dashboard hanya membaca penanda "Baru"; yang menandai terbaca adalah
    // halaman Pengumuman.
    const pengumumanBaru = pembacaBaru();
    let detailPengumuman = $state<PengumumanDetail | null>(null);
    let deviceUuid = $state<string | null>(null);
    let jam = $state(waktuSekarang());

    const tanggalPanjang = new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    function waktuSekarang(): string {
        return new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(new Date());
    }

    // Selisih jam HP dengan server, dihitung ulang setiap props dimuat.
    const selisihMs = $derived(new Date(waktuServer).getTime() - Date.now());
    let detik = $state(Date.now());
    const menitServer = $derived(jamServer(waktuServer, selisihMs, detik));

    $effect(() => {
        const jeda = setInterval(() => {
            const menitSebelum = jamServer(waktuServer, selisihMs, detik);

            jam = waktuSekarang();
            detik = Date.now();

            // Lewat tengah malam: status dan jadwal kemarin sudah basi.
            if (jamServer(waktuServer, selisihMs, detik) < menitSebelum - 60) {
                router.reload();
            }
        }, 1000);

        return () => clearInterval(jeda);
    });

    /*
     * PWA sering dibuka lagi dari background tanpa dimuat ulang. Tanpa ini
     * guru bisa melihat status kemarin -- mis. "absen sudah lengkap" padahal
     * belum absen hari ini.
     */
    $effect(() => {
        let tersembunyiSejak = 0;

        const saatBerganti = (): void => {
            if (document.hidden) {
                tersembunyiSejak = Date.now();

                return;
            }

            if (tersembunyiSejak && Date.now() - tersembunyiSejak > 60_000) {
                router.reload();
            }
        };

        document.addEventListener('visibilitychange', saatBerganti);

        return () =>
            document.removeEventListener('visibilitychange', saatBerganti);
    });

    $effect(() => {
        const uuid = bacaDeviceUuid(perangkatUuidTersimpan) ?? buatDeviceUuid();
        simpanDeviceUuid(uuid);
        deviceUuid = uuid;

        // Didaftarkan hanya kalau server belum mengenal uuid ini -- entah karena
        // baru, atau karena barisnya hilang (dihapus admin, database di-reset).
        // Tanpa cabang ini browser menyimpan uuid tak terdaftar selamanya dan
        // setiap tap ditolak "HP ini belum terdaftar" tanpa jalan keluar.
        const dikenalServer =
            statusPerangkat !== null && perangkatUuidTersimpan === uuid;

        if (dikenalServer || uuidTerkirim === uuid) {
            return;
        }

        uuidTerkirim = uuid;

        router.post(
            daftarkanPerangkat.url(),
            { device_uuid: uuid },
            { preserveScroll: true },
        );
    });

    const sudahMasuk = $derived(hariIni?.jam_masuk != null);
    const sudahPulang = $derived(hariIni?.jam_pulang != null);
    const selesai = $derived(sudahMasuk && sudahPulang);
    const jendela = $derived(
        statusJendela(jadwal, libur, sudahMasuk, menitServer),
    );

    const labelStatus: Record<string, string> = {
        hadir: 'Tepat waktu',
        terlambat: 'Terlambat',
    };

    /** Jam dinding dipecah: jam:menit besar, detik kecil di sampingnya. */
    const bagianJam = $derived(jam.split(/[.:]/));

    const garis = $derived(
        jadwal && jadwal.is_hari_kerja && !libur
            ? segmenJendela(jadwal, jadwal.toleransi_menit, menitServer)
            : null,
    );

    const warnaKeterangan = {
        tepat: 'text-primary',
        telat: 'text-[var(--g-yellow-ink-2)]',
        netral: 'text-muted-foreground',
    } as const;

    /** Pengumuman yang sedang terlihat di deretan geser, untuk titik halaman. */
    let posisiPengumuman = $state(0);

    function saatDigeser(event: Event): void {
        const deret = event.currentTarget as HTMLElement;
        const kartu = deret.querySelector('li');
        const langkah = kartu ? kartu.offsetWidth + 12 : 1;

        posisiPengumuman = Math.min(
            pengumumans.length - 1,
            Math.round(deret.scrollLeft / langkah),
        );
    }
</script>

<AppHead title="Absensi" />

<div
    class="mx-auto flex w-full max-w-3xl flex-col gap-4 px-4 py-5 safe-bottom sm:px-6"
>
    <InstallPrompt />

    <section
        aria-label="Jam dan jadwal"
        class="muncul grid gap-1 px-1"
        style="--i: 0"
    >
        <p
            class="font-mono text-xs tracking-[0.06em] text-muted-foreground uppercase"
        >
            {tanggalPanjang.format(new Date())}
        </p>
        <p class="flex items-baseline gap-1.5" aria-label="Pukul {jam}">
            <span
                class="font-display text-[clamp(4rem,22vw,5.5rem)] leading-[0.95] font-extrabold tracking-[-0.03em] tabular-nums"
                aria-hidden="true">{bagianJam[0]}:{bagianJam[1]}</span
            >
            <span
                class="font-mono text-xl text-muted-foreground tabular-nums"
                aria-hidden="true">{bagianJam[2]}</span
            >
        </p>

        {#if libur}
            <p class="text-sm text-muted-foreground">
                Hari ini libur: {libur}.
            </p>
        {:else if jadwal && jadwal.is_hari_kerja}
            <p class="text-sm text-muted-foreground">
                Masuk {jadwal.jam_masuk} · Pulang {jadwal.jam_pulang} · toleransi
                {jadwal.toleransi_menit} menit
            </p>
        {:else if jadwal}
            <p class="text-sm text-muted-foreground">
                Hari ini bukan hari kerjamu.
            </p>
        {:else}
            <p class="text-sm text-muted-foreground">
                Jadwal hari ini belum diatur TU. Absen tetap bisa dilakukan.
            </p>
        {/if}

        {#if lokasis.length > 0}
            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <MapPin class="size-3.5" aria-hidden="true" />
                Absen hanya di sekitar {lokasis
                    .map((lokasi) => lokasi.nama)
                    .join(', ')}
            </p>
        {/if}
    </section>

    {#if garis && jadwal}
        <section
            aria-labelledby="judul-jendela"
            class="muncul g-tile g-tone-plain gap-3 p-4"
            style="--i: 1"
        >
            <div class="flex items-baseline justify-between gap-2">
                <h2 id="judul-jendela" class="text-sm font-bold">
                    Jendela absen
                </h2>
                <span
                    class="text-[0.8125rem] font-semibold {warnaKeterangan[
                        garis.nada
                    ]}">{garis.keterangan}</span
                >
            </div>
            <div
                role="img"
                aria-label="Tepat waktu sampai {garis.batasTepat}, terlambat sampai {jadwal.tutup_masuk}, absen pulang mulai {jadwal.buka_pulang}."
                class="grid gap-1.5"
            >
                <div class="relative h-4">
                    <span
                        class="absolute inset-x-0 top-[5px] h-1.5 rounded-full bg-muted"
                    ></span>
                    <span
                        class="absolute top-[5px] left-0 h-1.5 rounded-l-full bg-primary"
                        style="width: {garis.tepatSampai}%"
                    ></span>
                    <span
                        class="absolute top-[5px] h-1.5 bg-[var(--g-amber)]"
                        style="left: {garis.tepatSampai}%; width: {garis.terlambatSampai -
                            garis.tepatSampai}%"
                    ></span>
                    <span
                        class="absolute top-[5px] right-0 h-1.5 rounded-r-full bg-[var(--g-sky-ink-2)]"
                        style="left: {garis.pulangMulai}%"
                    ></span>
                    <span
                        class="penanda absolute top-0 -ml-2 size-4 rounded-full border-[3px] border-foreground bg-card"
                        style="left: {garis.sekarang}%"
                    ></span>
                </div>
                <div
                    class="relative h-4 font-mono text-[0.6875rem] text-muted-foreground"
                    aria-hidden="true"
                >
                    <span
                        class="absolute -translate-x-1/2"
                        style="left: {garis.tepatSampai}%"
                        >{garis.batasTepat}</span
                    >
                    <span
                        class="absolute -translate-x-1/2"
                        style="left: {garis.terlambatSampai}%"
                        >{jadwal.tutup_masuk}</span
                    >
                    <span
                        class="absolute -translate-x-1/2"
                        style="left: {garis.pulangMulai}%"
                        >{jadwal.buka_pulang}</span
                    >
                </div>
            </div>
            <ul
                class="flex flex-wrap gap-x-3.5 gap-y-1 text-xs text-muted-foreground"
            >
                <li class="flex items-center gap-1.5">
                    <span class="size-2 rounded-[2px] bg-primary"></span>Tepat
                    waktu
                </li>
                <li class="flex items-center gap-1.5">
                    <span class="size-2 rounded-[2px] bg-[var(--g-amber)]"
                    ></span>Terlambat
                </li>
                <li class="flex items-center gap-1.5">
                    <span class="size-2 rounded-[2px] bg-[var(--g-sky-ink-2)]"
                    ></span>Absen pulang
                </li>
            </ul>
        </section>
    {/if}

    {#if statusPerangkat === 'pending'}
        <section class="g-tile g-tone-yellow gap-2">
            <div class="flex items-center gap-2">
                <ShieldAlert class="size-4" aria-hidden="true" />
                <h3 class="text-base">HP ini menunggu persetujuan</h3>
            </div>
            <p class="text-sm">
                Permintaan ganti HP sudah masuk. Absen baru bisa dipakai setelah
                TU menyetujui HP ini.
            </p>
        </section>
    {:else if statusPerangkat === null && page.props.errors.device_uuid}
        <!-- Pendaftaran HP ditolak (mis. uuid di browser ini milik guru lain).
             Tanpa pesan ini tombol absen hanya terlihat mati tanpa alasan. -->
        <section class="g-tile g-tone-red gap-2" role="alert">
            <div class="flex items-center gap-2">
                <ShieldAlert class="size-4" aria-hidden="true" />
                <h3 class="text-base">HP ini belum bisa dipakai absen</h3>
            </div>
            <p class="text-sm">{page.props.errors.device_uuid}</p>
        </section>
    {:else if statusPerangkat === 'revoked'}
        <section class="g-tile g-tone-red gap-2">
            <div class="flex items-center gap-2">
                <ShieldAlert class="size-4" aria-hidden="true" />
                <h3 class="text-base">HP ini dicabut</h3>
            </div>
            <p class="text-sm">
                HP ini tidak lagi diizinkan untuk absen. Hubungi TU kalau ini
                memang HP kamu.
            </p>
        </section>
    {/if}

    <div class="muncul grid gap-2.5" style="--i: 2">
        {#if selesai || (sudahMasuk && !jendela.bisa)}
            <!-- Sudah tercatat: tanda selesai, bukan tombol mati. -->
            <div class="tercatat" role="status">
                <span class="tercatat-ikon" aria-hidden="true">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.6"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="size-7"
                        ><path class="centang" d="M20 6 9 17l-5-5" /></svg
                    >
                </span>
                <span class="grid min-w-0 gap-1">
                    <span
                        class="font-display text-2xl leading-none font-extrabold"
                        >{selesai
                            ? 'Absen hari ini lengkap'
                            : `Masuk tercatat ${hariIni?.jam_masuk ?? ''}`}</span
                    >
                    <span class="text-[0.8125rem] text-[var(--g-green-ink-2)]">
                        {#if selesai}
                            Masuk {hariIni?.jam_masuk} · Pulang {hariIni?.jam_pulang}
                        {:else}
                            {hariIni?.status
                                ? (labelStatus[hariIni.status] ??
                                  hariIni.status)
                                : 'Tercatat'}{!jendela.bisa
                                ? ` · ${jendela.pesan}`
                                : ''}
                        {/if}
                    </span>
                </span>
            </div>
        {:else}
            <TapButton
                tipe={sudahMasuk ? 'pulang' : 'masuk'}
                label={sudahMasuk ? 'TAP PULANG' : 'TAP MASUK'}
                {punyaPasskey}
                {deviceUuid}
                disabled={deviceUuid === null ||
                    statusPerangkat !== 'active' ||
                    !jendela.bisa}
                pulangCepatSebelum={jendela.bisa &&
                jendela.konfirmasiPulangCepat
                    ? (jadwal?.jam_pulang ?? null)
                    : null}
            />

            {#if !jendela.bisa}
                <!-- Tombol mati selalu disertai alasan dan langkah berikutnya. -->
                <section class="g-tile g-tone-plain gap-1" role="status">
                    <h3 class="text-base">{jendela.judul}</h3>
                    <p class="text-sm">{jendela.pesan}</p>
                    {#if jendela.sarankanIzin}
                        <Link
                            href={toUrl(izinIndex())}
                            class="press mt-1 inline-flex min-h-11 items-center self-start rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
                            >Ajukan izin</Link
                        >
                    {/if}
                </section>
            {:else if statusPerangkat === null && !page.props.errors.device_uuid}
                <p
                    class="text-center text-sm text-muted-foreground"
                    role="status"
                >
                    Mendaftarkan HP ini… Kalau tombol tetap mati, muat ulang
                    halaman.
                </p>
            {/if}
        {/if}

        {#if masukKelas}
            <MasukKelasKartu {masukKelas} {deviceUuid} {menitServer} />
        {/if}
    </div>

    <section
        aria-labelledby="judul-status"
        aria-live="polite"
        class="muncul g-tile g-tone-plain gap-0 p-0"
        style="--i: 3"
    >
        <h2 id="judul-status" class="px-4 pt-3.5 pb-2 text-sm font-bold">
            Status hari ini
        </h2>
        <ul>
            <li
                class="flex min-h-12 items-center justify-between gap-3 border-t border-border px-4"
            >
                <span class="text-sm font-semibold">Tap masuk</span>
                <span class="flex flex-wrap items-center justify-end gap-1.5">
                    {#if hariIni?.jam_masuk}
                        {#key hariIni.jam_masuk}
                            <span
                                class="letup pil {hariIni.status === 'terlambat'
                                    ? 'bg-[var(--g-yellow-c)] text-[var(--g-yellow-ink-2)]'
                                    : 'bg-[var(--g-green-c)] text-[var(--g-green-ink)]'}"
                                >{hariIni.jam_masuk} · {labelStatus[
                                    hariIni.status ?? ''
                                ] ?? 'tercatat'}</span
                            >
                        {/key}
                        {#if !hariIni.terverifikasi}
                            <Badge variant="outline" class="gap-1">
                                <ShieldAlert
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                Tanpa biometrik
                            </Badge>
                        {/if}
                    {:else}
                        <span
                            class="pil border border-dashed border-[var(--g-ink-2)]/60 text-muted-foreground"
                            >Belum</span
                        >
                    {/if}
                </span>
            </li>
            {#if masukKelas}
                <li
                    class="flex min-h-12 items-center justify-between gap-3 border-t border-border px-4"
                >
                    <span class="text-sm font-semibold">Masuk kelas</span>
                    {#if masukKelas.absen}
                        <span
                            class="letup pil {masukKelas.absen.menitTerlambat >
                            0
                                ? 'bg-[var(--g-yellow-c)] text-[var(--g-yellow-ink-2)]'
                                : 'bg-[var(--g-green-c)] text-[var(--g-green-ink)]'}"
                            >{masukKelas.absen.kelas} · {masukKelas.absen
                                .jam}{masukKelas.absen.menitTerlambat > 0
                                ? ` · telat ${masukKelas.absen.menitTerlambat} mnt`
                                : ''}</span
                        >
                    {:else}
                        <span
                            class="text-right text-[0.8125rem] text-muted-foreground"
                            >batas {masukKelas.batas}{masukKelas.sudahTapMasuk
                                ? ''
                                : ' · setelah tap masuk'}</span
                        >
                    {/if}
                </li>
            {/if}
            <li
                class="flex min-h-12 items-center justify-between gap-3 border-t border-border px-4"
            >
                <span class="text-sm font-semibold">Tap pulang</span>
                <span class="flex flex-wrap items-center justify-end gap-1.5">
                    {#if hariIni?.jam_pulang}
                        <span
                            class="letup pil bg-[var(--g-sky-c)] text-[var(--g-sky-ink)]"
                            >{hariIni.jam_pulang}</span
                        >
                        {#if hariIni.pulang_cepat}
                            <Badge variant="outline">Pulang cepat</Badge>
                        {/if}
                    {:else if jadwal && jadwal.is_hari_kerja}
                        <span class="text-[0.8125rem] text-muted-foreground"
                            >buka {jadwal.buka_pulang}</span
                        >
                    {:else}
                        <span class="text-[0.8125rem] text-muted-foreground"
                            >-</span
                        >
                    {/if}
                </span>
            </li>
            <li class="border-t border-border px-4 py-2.5">
                <JarakLokasi {lokasis} />
            </li>
        </ul>
    </section>

    {#if pengumumans.length > 0}
        <section
            class="muncul grid gap-3"
            style="--i: 4"
            aria-labelledby="judul-pengumuman"
        >
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <Megaphone class="size-4 text-primary" aria-hidden="true" />
                    <h2 id="judul-pengumuman" class="text-sm font-bold">
                        Pengumuman
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    {#if pengumumans.length > 1}
                        <span
                            class="flex items-center gap-1"
                            aria-label="Posisi pengumuman"
                            role="img"
                        >
                            {#each pengumumans as pengumuman, indeks (pengumuman.id)}
                                <span
                                    class="h-1.5 rounded-full transition-[width,background-color] duration-(--dur) ease-(--spring) {indeks ===
                                    posisiPengumuman
                                        ? 'w-4.5 bg-foreground'
                                        : 'w-1.5 bg-[var(--g-line)]'}"
                                ></span>
                            {/each}
                        </span>
                    {/if}
                    <Link
                        href={toUrl(pengumumanIndex())}
                        class="-my-2 inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-primary hover:bg-primary/10"
                        >Lihat semua</Link
                    >
                </div>
            </div>

            <!-- Kartu berikutnya sengaja mengintip di tepi kanan: tanda
                 deretan ini bisa digeser. -->
            <ul
                class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto overscroll-x-contain px-4 pb-2 [scrollbar-width:none] sm:-mx-6 sm:scroll-px-6 sm:px-6 [&::-webkit-scrollbar]:hidden"
                aria-label="Pengumuman terbaru"
                onscroll={saatDigeser}
            >
                {#each pengumumans as pengumuman, indeks (pengumuman.id)}
                    <li class="flex w-[62%] max-w-60 shrink-0 snap-start">
                        <PengumumanKartu
                            class="w-full"
                            {pengumuman}
                            baru={pengumumanBaru(pengumuman)}
                            onclick={() => detailPengumuman?.buka(indeks)}
                        />
                    </li>
                {/each}
            </ul>
        </section>

        <PengumumanDetail
            bind:this={detailPengumuman}
            {pengumumans}
            baru={pengumumanBaru}
        />
    {/if}
</div>

<style>
    /* Bagian beranda naik berurutan saat dibuka. */
    .muncul {
        animation: naik 0.74s var(--spring) backwards;
        animation-delay: calc(var(--i) * 60ms);
    }

    /* Penanda "sekarang" bergeser halus tiap menit. */
    .penanda {
        transition: left 0.6s var(--spring);
    }

    .pil {
        display: inline-flex;
        align-items: center;
        height: 1.5rem;
        padding: 0 0.625rem;
        border-radius: 999px;
        font-family: var(--font-mono);
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .letup {
        animation: letup 0.46s ease-out 0.1s backwards;
    }

    .tercatat {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-height: 6rem;
        padding: 1.125rem 1.25rem;
        border: 1px solid var(--g-green-line);
        border-radius: 12px;
        background: var(--g-green-c);
        color: var(--g-green-ink);
        animation: naik 0.5s var(--spring) backwards;
    }

    .tercatat-ikon {
        display: grid;
        flex: none;
        place-items: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 999px;
        background: var(--g-blue);
        color: var(--g-on-blue);
        animation: letup 0.52s ease-out backwards;
    }

    /* Centang tergambar sekali, seperti tanda selesai di iOS. */
    .centang {
        stroke-dasharray: 24;
        animation: gambar 0.46s var(--spring) 0.16s backwards;
    }

    @keyframes naik {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
    }

    @keyframes letup {
        0% {
            opacity: 0;
            transform: scale(0.6);
        }
        55% {
            opacity: 1;
            transform: scale(1.14);
        }
    }

    @keyframes gambar {
        from {
            stroke-dashoffset: 24;
        }
    }
</style>
