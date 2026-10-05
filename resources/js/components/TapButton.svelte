<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import type { PasskeyError } from '@laravel/passkeys';
    import { usePasskeyVerify } from '@laravel/passkeys/svelte';
    import ArrowRight from 'lucide-svelte/icons/arrow-right';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Fingerprint from 'lucide-svelte/icons/fingerprint';
    import MapPin from 'lucide-svelte/icons/map-pin';
    import { store as tapAbsensi } from '@/actions/App/Http/Controllers/AbsensiController';
    import {
        index as passkeyOptions,
        store as passkeyVerifyRoute,
    } from '@/actions/App/Http/Controllers/AbsensiPasskeyController';
    import KonfirmasiDialog from '@/components/KonfirmasiDialog.svelte';
    import type { Konfirmasi } from '@/components/KonfirmasiDialog.svelte';
    import { Spinner } from '@/components/ui/spinner';
    import { letupan } from '@/lib/letupan';

    type Props = {
        tipe: 'masuk' | 'pulang';
        label: string;
        punyaPasskey: boolean;
        deviceUuid: string | null;
        disabled?: boolean;
        /** Jam pulang terjadwal, kalau tap sekarang akan tercatat pulang cepat. */
        pulangCepatSebelum?: string | null;
    };

    let {
        tipe,
        label,
        punyaPasskey,
        deviceUuid,
        disabled = false,
        pulangCepatSebelum = null,
    }: Props = $props();

    /**
     * Tahap yang sedang berjalan. Label tombol mengikutinya, jadi guru tahu
     * sedang menunggu GPS, sidik jari, atau server -- bukan "membaca lokasi"
     * terus-menerus.
     */
    let tahap = $state<'lokasi' | 'verifikasi' | 'kirim' | null>(null);
    const sedangProses = $derived(tahap !== null);
    const labelTahap = {
        lokasi: 'Membaca lokasi…',
        verifikasi: 'Verifikasi sidik jari…',
        kirim: 'Mengirim…',
    } as const;
    /** Baris kecil di bawah label: cara tap ini diverifikasi. */
    const keterangan = $derived(
        punyaPasskey
            ? 'Sidik jari · lokasi GPS'
            : 'Lokasi GPS · tanpa biometrik',
    );
    let konfirmasi = $state<Konfirmasi | null>(null);
    let pesanGalat = $state('');
    let posisi: GeolocationPosition | null = null;
    let tombol = $state<HTMLButtonElement | null>(null);

    const passkeyVerify = usePasskeyVerify({
        routes: {
            options: passkeyOptions.url(),
            submit: passkeyVerifyRoute.url(),
        },
        onSuccess: () => kirim(),
        onError: (galat: PasskeyError) => {
            // Pesan pustaka passkey berbahasa Inggris dan teknis.
            pesanGalat = /cancel|abort|not ?allowed/i.test(galat.message ?? '')
                ? 'Verifikasi sidik jari dibatalkan. Tap lagi untuk mencoba.'
                : 'Verifikasi sidik jari gagal. Coba lagi, atau hubungi TU kalau terus gagal.';
            tahap = null;
        },
    });

    function ambilPosisi(): Promise<GeolocationPosition> {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10_000,
                maximumAge: 0,
            });
        });
    }

    function kirim(): void {
        if (!posisi || !deviceUuid) {
            tahap = null;

            return;
        }

        tahap = 'kirim';

        // Dicatat sebelum kirim: setelah berhasil, halaman memuat ulang props
        // dan tombol ini bisa sudah hilang saat onSuccess berjalan.
        const kotak = tombol?.getBoundingClientRect();

        router.post(
            tapAbsensi.url(),
            {
                tipe,
                latitude: posisi.coords.latitude,
                longitude: posisi.coords.longitude,
                accuracy: Math.round(posisi.coords.accuracy),
                device_uuid: deviceUuid,
            },
            {
                preserveScroll: true,
                onSuccess: () => rayakan(kotak),
                onError: (errors: Record<string, string>) => {
                    pesanGalat =
                        errors.rate_limit ??
                        errors.tap ??
                        'Absen gagal. Coba lagi.';
                },
                // false: pesan tampil di sini, bukan toast global atau modal.
                onNetworkError: () => {
                    pesanGalat =
                        'Koneksi terputus, absen belum tercatat. Periksa sinyal lalu tap lagi.';

                    return false;
                },
                onHttpException: () => {
                    pesanGalat =
                        'Server sedang bermasalah, absen belum tercatat. Coba lagi sebentar lagi.';

                    return false;
                },
                onFinish: () => {
                    tahap = null;
                },
            },
        );
    }

    /** Percobaan001: letupan dari tengah tombol, hanya saat absen berhasil. */
    function rayakan(kotak: DOMRect | undefined): void {
        if (!page.props.percobaan?.percobaan001 || kotak === undefined) {
            return;
        }

        letupan(kotak.left + kotak.width / 2, kotak.top + kotak.height / 2);
    }

    function tekan(): void {
        if (pulangCepatSebelum) {
            konfirmasi = {
                judul: 'Belum jam pulang',
                pesan: `Jam pulangmu ${pulangCepatSebelum}. Kalau tetap absen sekarang, akan tercatat pulang cepat.`,
                label: 'Tetap absen pulang',
                destruktif: false,
                aksi: () => void tap(),
            };

            return;
        }

        void tap();
    }

    async function tap(): Promise<void> {
        pesanGalat = '';

        if (!navigator.onLine) {
            pesanGalat = 'Butuh koneksi internet untuk absen.';

            return;
        }

        if (!deviceUuid) {
            pesanGalat = 'HP ini belum terdaftar. Muat ulang halaman.';

            return;
        }

        if (punyaPasskey && !passkeyVerify.isSupported) {
            pesanGalat =
                'Browser di HP ini tidak mendukung verifikasi sidik jari. Buka lewat Chrome atau Safari terbaru, atau hubungi TU.';

            return;
        }

        tahap = 'lokasi';

        try {
            posisi = await ambilPosisi();
        } catch (galat) {
            tahap = null;
            pesanGalat = pesanLokasi(galat);

            return;
        }

        if (punyaPasskey) {
            tahap = 'verifikasi';
            // onSuccess akan memanggil kirim().
            passkeyVerify.verify();

            return;
        }

        kirim();
    }

    function pesanLokasi(galat: unknown): string {
        const kode = (galat as GeolocationPositionError | undefined)?.code;

        if (kode === 1) {
            return 'Izin lokasi ditolak. Buka Pengaturan aplikasi, aktifkan Lokasi dan Lokasi Tepat.';
        }

        if (kode === 3) {
            return 'GPS terlalu lama merespons. Coba di luar ruangan.';
        }

        return 'Lokasi tidak terbaca. Aktifkan GPS lalu coba lagi.';
    }
</script>

<div class="grid gap-3">
    <button
        bind:this={tombol}
        type="button"
        class="tap"
        onclick={tekan}
        disabled={disabled || sedangProses}
        aria-busy={sedangProses}
    >
        <span class="tap-ikon" aria-hidden="true">
            {#if tahap}
                <Spinner />
            {:else if punyaPasskey}
                <Fingerprint class="size-8" />
            {:else}
                <MapPin class="size-8" />
            {/if}
        </span>
        <span class="tap-teks">
            <span class="tap-label">{tahap ? labelTahap[tahap] : label}</span>
            <span class="tap-sub">{keterangan}</span>
        </span>
        {#if !tahap}
            <ArrowRight class="size-5 shrink-0" aria-hidden="true" />
        {/if}
    </button>

    {#if pesanGalat}
        <p
            class="flex items-start gap-2 rounded-xl border border-[var(--g-red-line)] bg-[var(--g-red-c)] px-4 py-3 text-sm font-medium text-[var(--g-red-ink)]"
            role="alert"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            {pesanGalat}
        </p>
    {/if}
</div>

<KonfirmasiDialog bind:permintaan={konfirmasi} />

<style>
    /* Tombol tap desain A: selebar layar, rata kiri, tekan lalu memantul. */
    .tap {
        display: flex;
        align-items: center;
        gap: 1rem;
        width: 100%;
        min-height: 6rem;
        padding: 1.125rem 1.25rem;
        border-radius: 12px;
        background: var(--g-blue);
        color: var(--g-on-blue);
        text-align: left;
        /* Cegah double-tap zoom, seleksi teks, dan kilatan tap di mobile. */
        touch-action: manipulation;
        user-select: none;
        transition:
            transform var(--dur) var(--spring),
            opacity 0.2s ease;
    }

    .tap:active:not(:disabled) {
        transform: scale(0.97);
        transition-duration: 90ms;
    }

    .tap:disabled {
        opacity: 0.55;
    }

    .tap-ikon {
        display: grid;
        flex: none;
        place-items: center;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 999px;
        background: color-mix(in srgb, var(--g-on-blue) 14%, transparent);
    }

    .tap-teks {
        display: grid;
        flex: 1;
        gap: 0.3rem;
        min-width: 0;
    }

    .tap-label {
        font-family: var(--font-display);
        font-size: 1.625rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: 0.02em;
    }

    .tap-sub {
        font-size: 0.8125rem;
        opacity: 0.85;
    }
</style>
