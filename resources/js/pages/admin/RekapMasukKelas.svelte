<script module lang="ts">
    export const layout = {
        breadcrumbs: [
            { title: 'Rekap', href: '/admin/rekap' },
            { title: 'Masuk kelas', href: '/admin/rekap/masuk-kelas' },
        ],
    };
</script>

<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';
    import Download from 'lucide-svelte/icons/download';
    import { foto } from '@/actions/App/Http/Controllers/Admin/MasukKelasController';
    import {
        exportMasukKelas,
        index as rekapIndex,
        masukKelas,
    } from '@/actions/App/Http/Controllers/Admin/RekapController';
    import AppHead from '@/components/AppHead.svelte';
    import type { GuruMasukKelas } from '@/components/MasukKelasPantauan.svelte';
    import { Button } from '@/components/ui/button';
    import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';

    type Status = 'tepat' | 'telat' | 'kosong';
    type Rincian = { tanggal: string; status: Status; guru: GuruMasukKelas[] };
    type Baris = {
        kelas_id: number;
        nama: string;
        unit: string;
        hari_efektif: number;
        tepat: number;
        telat: number;
        kosong: number;
        persentase: number;
        rincian: Rincian[];
    };
    type Mode = 'bulanan' | 'periode';

    let {
        filter,
        kantors,
        rekap,
    }: {
        filter: {
            mode: Mode;
            tahun: number;
            bulan: number;
            mulai: string;
            selesai: string;
            kantor_id: number | null;
        };
        kantors: { id: number; nama: string }[];
        rekap: Baris[];
    } = $props();

    let mode = $state<Mode>(filter.mode);
    let tahun = $state(filter.tahun);
    let bulan = $state(filter.bulan);
    let mulai = $state(filter.mulai);
    let selesai = $state(filter.selesai);
    let kantorId = $state<number | null>(filter.kantor_id);
    let dilihat = $state<Baris | null>(null);
    let terbuka = $state(false);

    const namaBulan = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];
    const formatTanggal = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'UTC',
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
    const label: Record<Status, string> = {
        tepat: 'Tepat',
        telat: 'Telat',
        kosong: 'Kosong',
    };
    const nada: Record<Status, string> = {
        tepat: 'g-tone-green',
        telat: 'g-tone-yellow',
        kosong: 'g-tone-red',
    };

    function query(): Record<string, string> {
        const params: Record<string, string> =
            mode === 'periode'
                ? { mode, mulai, selesai }
                : { mode, tahun: String(tahun), bulan: String(bulan) };

        if (kantorId) {
            params.kantor_id = String(kantorId);
        }

        return params;
    }

    function terapkan(): void {
        router.get(masukKelas.url(), query(), {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function unduh(): void {
        window.location.href = exportMasukKelas.url({ query: query() });
    }
</script>

<AppHead title="Rekap masuk kelas" />
<div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-4 py-5 sm:px-6">
    <nav class="flex gap-2" aria-label="Jenis rekap">
        <Link
            href={rekapIndex.url()}
            class="inline-flex min-h-11 items-center rounded-full px-4 text-sm font-semibold hover:bg-muted"
            >Absensi guru</Link
        >
        <span
            class="inline-flex min-h-11 items-center rounded-full bg-primary px-4 text-sm font-semibold text-primary-foreground"
            aria-current="page">Masuk kelas</span
        >
    </nav>

    <section class="g-tile g-tone-plain gap-3">
        <h3>Rekap masuk kelas</h3>
        <div class="flex flex-wrap items-end gap-2">
            <select
                bind:value={mode}
                class="h-10 rounded-md border border-input bg-background px-3"
                aria-label="Jenis rekap"
            >
                <option value="bulanan">Bulanan</option>
                <option value="periode">Periode</option>
            </select>
            {#if mode === 'periode'}
                <input
                    type="date"
                    bind:value={mulai}
                    max={selesai}
                    class="h-10 rounded-md border border-input bg-background px-3"
                    aria-label="Tanggal mulai"
                />
                <span class="self-center text-muted-foreground">s.d.</span>
                <input
                    type="date"
                    bind:value={selesai}
                    min={mulai}
                    class="h-10 rounded-md border border-input bg-background px-3"
                    aria-label="Tanggal selesai"
                />
            {:else}
                <select
                    bind:value={bulan}
                    class="h-10 rounded-md border border-input bg-background px-3"
                    aria-label="Bulan"
                >
                    {#each namaBulan as nama, index (nama)}<option
                            value={index + 1}>{nama}</option
                        >{/each}
                </select>
                <input
                    type="number"
                    bind:value={tahun}
                    min="2020"
                    max="2100"
                    class="h-10 w-24 rounded-md border border-input bg-background px-3"
                    aria-label="Tahun"
                />
            {/if}
            {#if kantors.length > 0}
                <select
                    bind:value={kantorId}
                    class="h-10 rounded-md border border-input bg-background px-3"
                    aria-label="Unit"
                >
                    <option value={null}>Semua unit</option>
                    {#each kantors as kantor (kantor.id)}<option
                            value={kantor.id}>{kantor.nama}</option
                        >{/each}
                </select>
            {/if}
            <Button onclick={terapkan}>Terapkan</Button>
            <Button variant="outline" onclick={unduh}
                ><Download class="size-4" aria-hidden="true" /> CSV</Button
            >
        </div>
        {#each Object.values(page.props.errors) as error, i (i)}<p
                class="text-sm text-destructive"
                role="alert"
            >
                {error}
            </p>{/each}
    </section>

    <section class="g-tile g-tone-plain">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="text-muted-foreground">
                        <th class="px-2 py-2 text-left font-medium">Kelas</th>
                        <th class="px-2 py-2 text-right font-medium"
                            >Hari efektif</th
                        >
                        <th class="px-2 py-2 text-right font-medium">Tepat</th>
                        <th class="px-2 py-2 text-right font-medium">Telat</th>
                        <th class="px-2 py-2 text-right font-medium">Kosong</th>
                        <th class="px-2 py-2 text-right font-medium">% Tepat</th
                        >
                    </tr>
                </thead>
                <tbody>
                    {#each rekap as baris (baris.kelas_id)}
                        <tr class="border-t border-border">
                            <th class="px-2 py-2 text-left font-medium">
                                <button
                                    type="button"
                                    class="min-h-11 text-left underline-offset-4 hover:underline"
                                    onclick={() => {
                                        dilihat = baris;
                                        terbuka = true;
                                    }}
                                    >{baris.nama}<span
                                        class="block text-xs font-normal text-muted-foreground"
                                        >{baris.unit}</span
                                    ></button
                                >
                            </th>
                            <td class="px-2 py-2 text-right tabular-nums"
                                >{baris.hari_efektif}</td
                            >
                            <td class="px-2 py-2 text-right tabular-nums"
                                >{baris.tepat}</td
                            >
                            <td class="px-2 py-2 text-right tabular-nums"
                                >{baris.telat}</td
                            >
                            <td class="px-2 py-2 text-right tabular-nums"
                                >{baris.kosong}</td
                            >
                            <td
                                class="px-2 py-2 text-right font-semibold tabular-nums"
                                >{baris.persentase.toLocaleString('id-ID')}%</td
                            >
                        </tr>
                    {:else}
                        <tr
                            ><td
                                colspan="6"
                                class="px-2 py-4 text-center text-muted-foreground"
                                >Belum ada kelas aktif.</td
                            ></tr
                        >
                    {/each}
                </tbody>
            </table>
        </div>
    </section>
</div>

<Dialog bind:open={terbuka}>
    <DialogContent class="max-h-[90dvh] overflow-y-auto">
        <DialogTitle>{dilihat?.nama ?? ''}</DialogTitle>
        <ul class="mt-4 grid gap-3">
            {#each dilihat?.rincian ?? [] as hari (hari.tanggal)}
                <li class="g-tile gap-2 px-4 py-3 {nada[hari.status]}">
                    <p class="flex justify-between gap-2 text-sm font-semibold">
                        <span
                            >{formatTanggal.format(
                                new Date(hari.tanggal),
                            )}</span
                        >
                        <span>{label[hari.status]}</span>
                    </p>
                    {#each hari.guru as guru (guru.absensiKelasId)}
                        <div class="grid gap-1">
                            <p class="text-sm">
                                {guru.nama} · {guru.jam}{guru.menitTerlambat > 0
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
                            {/if}
                        </div>
                    {/each}
                </li>
            {:else}
                <li class="text-sm text-muted-foreground">
                    Belum ada hari efektif pada periode ini.
                </li>
            {/each}
        </ul>
    </DialogContent>
</Dialog>
