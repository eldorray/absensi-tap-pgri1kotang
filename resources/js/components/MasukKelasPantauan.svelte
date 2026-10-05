<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { foto } from '@/actions/App/Http/Controllers/Admin/MasukKelasController';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

    export type GuruMasukKelas = {
        nama: string;
        jam: string;
        menitTerlambat: number;
        absensiKelasId: number;
        adaFoto: boolean;
    };

    export type StatusKelas = 'tepat' | 'telat' | 'kosong' | 'menunggu';

    export type KelasPantauan = {
        id: number;
        nama: string;
        unit: string;
        status: StatusKelas;
        guru: GuruMasukKelas[];
    };

    export type PantauanMasukKelas = {
        batas: string;
        lewat: boolean;
        kelas: KelasPantauan[];
    };

    let {
        masukKelas,
        pantau = true,
    }: {
        masukKelas: PantauanMasukKelas;
        /** Muat ulang tiap 60 detik; matikan untuk tanggal lampau yang tidak berubah lagi. */
        pantau?: boolean;
    } = $props();

    let dilihat = $state<KelasPantauan | null>(null);
    let terbuka = $state(false);

    const label: Record<StatusKelas, string> = {
        tepat: 'Tepat',
        telat: 'Telat',
        kosong: 'Kosong',
        menunggu: 'Menunggu',
    };
    const nada: Record<StatusKelas, string> = {
        tepat: 'g-tone-green',
        telat: 'g-tone-yellow',
        kosong: 'g-tone-red',
        menunggu: 'g-tone-plain',
    };

    const terisi = $derived(
        masukKelas.kelas.filter((kelas) => kelas.guru.length > 0).length,
    );
    const perUnit = $derived.by(() => {
        const peta: Record<string, KelasPantauan[]> = {};

        for (const kelas of masukKelas.kelas) {
            (peta[kelas.unit] ??= []).push(kelas);
        }

        return Object.entries(peta);
    });

    function keterangan(kelas: KelasPantauan): string {
        const pertama = kelas.guru[0];

        if (!pertama) {
            return kelas.status === 'kosong' ? 'Belum ada guru' : 'Menunggu';
        }

        return pertama.menitTerlambat > 0
            ? `${pertama.nama} · ${pertama.jam} · +${pertama.menitTerlambat} menit`
            : `${pertama.nama} · ${pertama.jam}`;
    }

    // ponytail: muat ulang prop ini saja tiap 60 detik; controller tetap
    // menghitung seluruh dashboard per permintaan. Jadikan prop lain closure
    // kalau dashboard terasa berat.
    $effect(() => {
        if (!pantau) {
            return;
        }

        const jeda = setInterval(() => {
            if (!document.hidden) {
                router.reload({ only: ['masukKelas'] });
            }
        }, 60_000);

        return () => clearInterval(jeda);
    });
</script>

<section class="g-tile g-tone-plain gap-3">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h3>Masuk kelas</h3>
        <p class="text-sm text-muted-foreground">
            Batas {masukKelas.batas} · {terisi}/{masukKelas.kelas.length} kelas terisi
        </p>
    </div>

    {#each perUnit as [unit, daftar] (unit)}
        <div class="grid gap-2">
            {#if perUnit.length > 1}
                <p
                    class="text-xs font-semibold tracking-wide uppercase text-muted-foreground"
                >
                    {unit}
                </p>
            {/if}
            <ul
                class="grid grid-cols-[repeat(auto-fill,minmax(9.5rem,1fr))] gap-2"
            >
                {#each daftar as kelas (kelas.id)}
                    <li>
                        <button
                            type="button"
                            class="g-tile lift w-full gap-1 px-3.5 py-3 text-left {nada[
                                kelas.status
                            ]}"
                            disabled={kelas.guru.length === 0}
                            onclick={() => {
                                dilihat = kelas;
                                terbuka = true;
                            }}
                        >
                            <span
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="font-display text-xl leading-none font-extrabold"
                                    >{kelas.nama}</span
                                >
                                <span class="text-xs font-semibold uppercase"
                                    >{label[kelas.status]}</span
                                >
                            </span>
                            <span class="font-mono text-xs font-semibold"
                                >{keterangan(kelas)}</span
                            >
                        </button>
                    </li>
                {/each}
            </ul>
        </div>
    {:else}
        <p class="text-muted-foreground">Belum ada kelas aktif.</p>
    {/each}
</section>

<Dialog bind:open={terbuka}>
    <DialogContent class="max-h-[90dvh] overflow-y-auto">
        <DialogTitle>{dilihat?.nama ?? ''}</DialogTitle>
        <ul class="mt-4 grid gap-4">
            {#each dilihat?.guru ?? [] as guru (guru.absensiKelasId)}
                <li class="grid gap-2">
                    <p class="text-sm">
                        <span class="font-semibold">{guru.nama}</span> · {guru.jam}{guru.menitTerlambat >
                        0
                            ? ` · telat ${guru.menitTerlambat} menit`
                            : ''}
                    </p>
                    {#if guru.adaFoto}
                        <img
                            src={foto.url(guru.absensiKelasId)}
                            alt="Foto {guru.nama} di {dilihat?.nama}"
                            loading="lazy"
                            class="w-full rounded-2xl"
                        />
                    {:else}
                        <p class="text-sm text-muted-foreground">
                            Foto sudah dihapus (lebih dari 60 hari).
                        </p>
                    {/if}
                </li>
            {/each}
        </ul>
    </DialogContent>
</Dialog>
