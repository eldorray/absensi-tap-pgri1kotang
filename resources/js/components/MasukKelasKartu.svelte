<script lang="ts">
    import Camera from 'lucide-svelte/icons/camera';
    import KameraSelfie from '@/components/KameraSelfie.svelte';
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

<!--
    Statusnya (sudah masuk kelas, batas, telat) tampil di daftar "Status hari
    ini" di beranda. Di sini hanya aksinya: muncul setelah tap masuk, selama
    guru belum mencatat masuk kelas.
-->
{#if masukKelas.sudahTapMasuk && !masukKelas.absen}
    <Button
        variant="outline"
        size="lg"
        class="w-full gap-2.5 bg-card"
        disabled={deviceUuid === null}
        onclick={() => (terbuka = true)}
    >
        <Camera class="size-5" aria-hidden="true" />
        Absen masuk kelas
        <span
            class="font-mono text-xs font-semibold {sisaMenit < 0
                ? 'text-destructive'
                : 'text-[var(--g-yellow-ink-2)]'}">{labelWaktu}</span
        >
    </Button>
{/if}

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
                ? `Foto di ${kelasDipilih.nama}`
                : 'Pilih kelas'}</DialogTitle
        >
        {#if kelasDipilih === null}
            <ul class="mt-4 grid gap-2">
                {#each masukKelas.kelas as kelas (kelas.id)}
                    <li>
                        <button
                            type="button"
                            class="flex min-h-11 w-full flex-wrap items-center justify-between gap-2 rounded-2xl border border-border px-4 py-2 text-left hover:bg-muted disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60"
                            disabled={kelas.terisiOleh.length > 0}
                            onclick={() => (kelasDipilih = kelas)}
                        >
                            <span class="font-semibold">{kelas.nama}</span>
                            {#if kelas.terisiOleh.length > 0}
                                <span class="text-xs text-muted-foreground"
                                    >sudah diisi {kelas.terisiOleh.join(
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
