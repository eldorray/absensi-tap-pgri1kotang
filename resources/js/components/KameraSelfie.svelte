<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { store as catatMasukKelas } from '@/actions/App/Http/Controllers/MasukKelasController';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';

    let {
        kelasId,
        deviceUuid,
        onbatal,
        onselesai,
    }: {
        kelasId: number;
        deviceUuid: string;
        onbatal: () => void;
        onselesai: () => void;
    } = $props();

    /** Sisi terpanjang foto setelah dikecilkan: ~150 KB, cepat di sinyal kelas. */
    const SISI_MAKS = 1280;

    let video = $state<HTMLVideoElement | null>(null);
    let stream: MediaStream | null = null;
    /** Naik setiap kamera dihentikan; izin yang baru dijawab sesudahnya dibuang. */
    let generasi = 0;
    let hadap = $state<'user' | 'environment'>('user');
    let galat = $state<string | null>(null);
    let pratinjau = $state<string | null>(null);

    const form = useForm<{
        kelas_id: number;
        foto: File | null;
        device_uuid: string;
    }>({ kelas_id: 0, foto: null, device_uuid: '' });

    function hentikan(): void {
        generasi++;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    /*
     * Sengaja tidak ada <input type="file">: upload dari galeri membuka celah
     * memakai foto kemarin. Kamera ditolak = tidak bisa absen kelas.
     */
    async function nyalakan(): Promise<void> {
        hentikan();
        const ke = generasi;
        galat = null;

        if (!navigator.mediaDevices?.getUserMedia) {
            galat =
                'Browser ini tidak bisa membuka kamera. Buka aplikasi lewat Chrome atau Safari terbaru.';

            return;
        }

        try {
            const baru = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: hadap },
                audio: false,
            });

            // Dialog ditutup atau kamera dibalik selama menunggu izin: tanpa
            // ini kamera tetap menyala di latar walau dialognya sudah hilang.
            if (ke !== generasi) {
                baru.getTracks().forEach((track) => track.stop());

                return;
            }

            stream = baru;

            if (video) {
                video.srcObject = stream;
                await video.play();
            }
        } catch {
            galat =
                'Kamera tidak bisa dibuka. Izinkan akses kamera untuk aplikasi ini di pengaturan browser, lalu coba lagi.';
        }
    }

    $effect(() => {
        // Dibaca supaya kamera menyala ulang saat dibalik atau saat foto diulang.
        void hadap;

        if (pratinjau === null) {
            void nyalakan();
        }

        return hentikan;
    });

    function jepret(): void {
        if (!video || video.videoWidth === 0) {
            return;
        }

        const skala = Math.min(
            1,
            SISI_MAKS / Math.max(video.videoWidth, video.videoHeight),
        );
        const kanvas = document.createElement('canvas');
        kanvas.width = Math.round(video.videoWidth * skala);
        kanvas.height = Math.round(video.videoHeight * skala);
        kanvas
            .getContext('2d')
            ?.drawImage(video, 0, 0, kanvas.width, kanvas.height);

        kanvas.toBlob(
            (blob) => {
                if (!blob) {
                    galat = 'Foto gagal diambil. Coba lagi.';

                    return;
                }

                form.foto = new File([blob], 'masuk-kelas.jpg', {
                    type: 'image/jpeg',
                });
                pratinjau = URL.createObjectURL(blob);
            },
            'image/jpeg',
            0.7,
        );
    }

    function ulang(): void {
        if (pratinjau) {
            URL.revokeObjectURL(pratinjau);
        }

        pratinjau = null;
        form.foto = null;
    }

    function kirim(): void {
        // Diisi saat kirim, bukan saat form dibuat: selalu nilai prop terbaru.
        form.kelas_id = kelasId;
        form.device_uuid = deviceUuid;
        form.post(catatMasukKelas.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                ulang();
                onselesai();
            },
        });
    }
</script>

<div class="mt-4 grid gap-3">
    {#if galat}
        <p class="text-sm text-destructive" role="alert">{galat}</p>
    {/if}

    {#if pratinjau}
        <img
            src={pratinjau}
            alt="Pratinjau foto masuk kelas"
            class="w-full rounded-2xl"
        />
    {/if}

    <!-- Selalu dirender supaya bind:this tetap ada saat kamera dinyalakan ulang. -->
    <video
        bind:this={video}
        playsinline
        muted
        hidden={pratinjau !== null || galat !== null}
        class="w-full rounded-2xl bg-black {hadap === 'user'
            ? '-scale-x-100'
            : ''}"
    ></video>

    {#each Object.values(form.errors) as error, i (i)}
        <InputError message={error} />
    {/each}

    <div class="flex flex-wrap gap-2">
        {#if pratinjau}
            <Button variant="outline" onclick={ulang} disabled={form.processing}
                >Ulang</Button
            >
            <Button onclick={kirim} disabled={form.processing}
                >{form.processing ? 'Mengirim…' : 'Kirim'}</Button
            >
        {:else if galat}
            <Button variant="outline" onclick={() => void nyalakan()}
                >Coba lagi</Button
            >
        {:else}
            <Button onclick={jepret}>Jepret</Button>
            <Button
                variant="outline"
                onclick={() =>
                    (hadap = hadap === 'user' ? 'environment' : 'user')}
                >Balik kamera</Button
            >
        {/if}
        <Button variant="ghost" onclick={onbatal} disabled={form.processing}
            >Ganti kelas</Button
        >
    </div>
</div>
