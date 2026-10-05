<script lang="ts">
    import Camera from 'lucide-svelte/icons/camera';
    import CircleCheck from 'lucide-svelte/icons/circle-check';
    import KameraSelfie from '@/components/KameraSelfie.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

    export type KelasPilihan = {
        id: number;
        nama: string;
        terisiOleh: string[];
    };

    export type MasukKelas = {
        batas: string;
        sudahTapMasuk: boolean;
        absen: { kelas: string; jam: string; menitTerlambat: number } | null;
        kelas: KelasPilihan[];
    };

    let {
        masukKelas,
        deviceUuid,
        menitServer,
    }: {
        masukKelas: MasukKelas;
        deviceUuid: string | null;
        /** Jam server dalam menit sejak tengah malam (lihat jamServer()). */
        menitServer: number;
    } = $props();

    let terbuka = $state(false);
    let kelasDipilih = $state<KelasPilihan | null>(null);

    function menitDari(jam: string): number {
        const [h, m] = jam.split(':').map(Number);

        return h * 60 + m;
    }

    const sisaMenit = $derived(menitDari(masukKelas.batas) - menitServer);
    const nada = $derived(
        masukKelas.absen
            ? masukKelas.absen.menitTerlambat > 0
                ? 'g-tone-yellow'
                : 'g-tone-green'
            : sisaMenit < 0
              ? 'g-tone-red'
              : 'g-tone-yellow',
    );
    const labelWaktu = $derived(
        sisaMenit > 0
            ? `${sisaMenit} menit lagi`
            : sisaMenit === 0
              ? 'Batas sekarang'
              : `Lewat ${-sisaMenit} menit`,
    );

    function tutup(): void {
        terbuka = false;
        kelasDipilih = null;
    }
</script>

<section class="g-tile gap-2 {nada}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-base">Masuk kelas</h3>
        {#if masukKelas.absen}
            <Badge variant="secondary"
                >{masukKelas.absen.menitTerlambat > 0
                    ? `Telat ${masukKelas.absen.menitTerlambat} menit`
                    : 'Tepat waktu'}</Badge
            >
        {:else}
            <Badge variant="outline">{labelWaktu}</Badge>
        {/if}
    </div>

    {#if masukKelas.absen}
        <p class="flex items-center gap-2 text-sm">
            <CircleCheck class="size-4" aria-hidden="true" />
            Masuk kelas {masukKelas.absen.kelas} pukul {masukKelas.absen.jam}.
        </p>
    {:else if !masukKelas.sudahTapMasuk}
        <p class="text-sm">
            Batas masuk kelas {masukKelas.batas}. Tap masuk dulu, lalu absen di
            kelas bersama siswa.
        </p>
    {:else}
        <p class="text-sm">
            Batas masuk kelas {masukKelas.batas}. Foto bersama siswa di kelas.
        </p>
        <Button
            class="min-h-11 self-start"
            disabled={deviceUuid === null}
            onclick={() => (terbuka = true)}
        >
            <Camera class="size-4" aria-hidden="true" />
            Absen masuk kelas
        </Button>
    {/if}
</section>

<Dialog
    bind:open={terbuka}
    onOpenChange={(buka) => {
        if (!buka) {
            kelasDipilih = null;
        }
    }}
>
    <DialogContent class="max-h-[90dvh] overflow-y-auto">
        <DialogTitle
            >{kelasDipilih
                ? `Foto di kelas ${kelasDipilih.nama}`
                : 'Pilih kelas'}</DialogTitle
        >
        {#if kelasDipilih === null}
            <ul class="mt-4 grid gap-2">
                {#each masukKelas.kelas as kelas (kelas.id)}
                    <li>
                        <button
                            type="button"
                            class="flex min-h-11 w-full flex-wrap items-center justify-between gap-2 rounded-2xl border border-border px-4 py-2 text-left hover:bg-muted"
                            onclick={() => (kelasDipilih = kelas)}
                        >
                            <span class="font-semibold">{kelas.nama}</span>
                            {#if kelas.terisiOleh.length > 0}
                                <span class="text-xs text-muted-foreground"
                                    >sudah ada {kelas.terisiOleh.join(
                                        ', ',
                                    )}</span
                                >
                            {/if}
                        </button>
                    </li>
                {:else}
                    <li class="text-sm text-muted-foreground">
                        Belum ada kelas aktif di unitmu. Hubungi TU.
                    </li>
                {/each}
            </ul>
        {:else if deviceUuid}
            <KameraSelfie
                kelasId={kelasDipilih.id}
                {deviceUuid}
                onbatal={() => (kelasDipilih = null)}
                onselesai={tutup}
            />
        {/if}
    </DialogContent>
</Dialog>
