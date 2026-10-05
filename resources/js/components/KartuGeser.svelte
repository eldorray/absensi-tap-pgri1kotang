<script lang="ts">
    import Check from 'lucide-svelte/icons/check';
    import X from 'lucide-svelte/icons/x';
    import type { Snippet } from 'svelte';
    import { Button } from '@/components/ui/button';
    import {
        hentikan,
        jalankan,
        karet,
        kecepatan,
        pegas,
        proyeksi,
    } from '@/lib/pegas';
    import type { Sampel } from '@/lib/pegas';

    type Keputusan = 'setujui' | 'tolak';

    let {
        labelSetujui = 'Setujui',
        onputus,
        children,
    }: {
        labelSetujui?: string;
        /** Dipanggil sesudah kartu terlempar; pemanggil yang mengirim ke server. */
        onputus: (keputusan: Keputusan) => void;
        children: Snippet;
    } = $props();

    /** Lewat jarak ini kartu "siaga": ikon di baliknya membesar. */
    const SIAGA = 110;
    /** Sampai batas ini kartu ikut jari 1:1, sesudahnya karet. */
    const BATAS = 150;

    let kartu = $state<HTMLElement | null>(null);
    let siaga = $state<Keputusan | null>(null);
    let terbang = $state(false);
    const p = pegas();
    let tarikan: {
        id: number;
        awalX: number;
        awalY: number;
        acuan: number;
        aktif: boolean;
        riwayat: Sampel[];
    } | null = null;

    function terapkan(x: number): void {
        if (kartu) {
            kartu.style.transform = x === 0 ? '' : `translate3d(${x}px, 0, 0)`;
        }
    }

    /** Terbang ke arah keputusan sambil membawa kecepatan jari, lalu lapor. */
    function lempar(keputusan: Keputusan, v = 0): void {
        if (terbang || !kartu) {
            return;
        }

        terbang = true;
        siaga = keputusan;
        const jauh = kartu.offsetWidth + 40;
        p.v = v;
        jalankan(
            p,
            keputusan === 'setujui' ? jauh : -jauh,
            { damping: 1, response: 0.3 },
            terapkan,
        );
        setTimeout(() => onputus(keputusan), 180);
    }

    /** Kirim gagal: kartu kembali lewat jalan yang sama. */
    export function kembalikan(): void {
        terbang = false;
        siaga = null;
        jalankan(p, 0, { damping: 1, response: 0.42 }, terapkan);
    }

    function turun(event: PointerEvent): void {
        const target = event.target as Element | null;

        if (
            terbang ||
            tarikan ||
            event.button !== 0 ||
            target?.closest('button, a')
        ) {
            return;
        }

        // Ditangkap sejak ditekan: tanpa ini, mouse yang dilepas di luar kartu
        // meninggalkan tarikan menggantung yang ikut kursor tanpa tombol.
        kartu?.setPointerCapture(event.pointerId);
        tarikan = {
            id: event.pointerId,
            awalX: event.clientX,
            awalY: event.clientY,
            acuan: event.clientX - p.x,
            aktif: false,
            riwayat: [{ x: p.x, t: performance.now() }],
        };
    }

    function gerak(event: PointerEvent): void {
        if (!tarikan || event.pointerId !== tarikan.id || !kartu) {
            return;
        }

        // Mouse tanpa tombol ditekan bukan tarikan, apa pun yang tercatat.
        if (event.pointerType === 'mouse' && (event.buttons & 1) === 0) {
            tarikan = null;
            jalankan(p, 0, { damping: 1, response: 0.4 }, terapkan);

            return;
        }

        if (!tarikan.aktif) {
            const mx = Math.abs(event.clientX - tarikan.awalX);
            const my = Math.abs(event.clientY - tarikan.awalY);

            if (mx < 10 && my < 10) {
                return;
            }

            // Gerak tegak: halaman yang menggulir, bukan kartu.
            if (my > mx) {
                tarikan = null;

                return;
            }

            tarikan.aktif = true;
            hentikan(p);
            tarikan.acuan = event.clientX - p.x;
        }

        const mentah = event.clientX - tarikan.acuan;
        const x =
            Math.abs(mentah) > BATAS
                ? Math.sign(mentah) *
                  (BATAS + karet(Math.abs(mentah) - BATAS, kartu.offsetWidth))
                : mentah;
        p.x = x;
        terapkan(x);
        siaga = x > SIAGA ? 'setujui' : x < -SIAGA ? 'tolak' : null;
        tarikan.riwayat.push({ x, t: performance.now() });

        if (tarikan.riwayat.length > 8) {
            tarikan.riwayat.shift();
        }
    }

    function lepas(event: PointerEvent): void {
        if (!tarikan || event.pointerId !== tarikan.id) {
            return;
        }

        const selesai = tarikan;
        tarikan = null;

        if (!selesai.aktif) {
            return;
        }

        selesai.riwayat.push({ x: p.x, t: performance.now() });
        const v = kecepatan(selesai.riwayat);
        const mendarat = p.x + proyeksi(v, 0.99);

        if (mendarat > 160) {
            lempar('setujui', v);

            return;
        }

        if (mendarat < -160) {
            lempar('tolak', v);

            return;
        }

        siaga = null;
        p.v = v;
        jalankan(p, 0, { damping: 0.8, response: 0.4 }, terapkan);
    }
</script>

<div class="relative overflow-hidden">
    <div
        aria-hidden="true"
        class="absolute inset-y-0 left-0 flex w-1/2 items-center bg-primary pl-5 text-primary-foreground"
    >
        <span
            class="inline-flex items-center gap-2 text-sm font-bold transition-transform duration-(--dur) ease-(--spring) {siaga ===
            'setujui'
                ? 'scale-110'
                : 'scale-90'}"
        >
            <Check class="size-5" strokeWidth={2.5} />{labelSetujui}
        </span>
    </div>
    <div
        aria-hidden="true"
        class="absolute inset-y-0 right-0 flex w-1/2 items-center justify-end bg-destructive pr-5 text-destructive-foreground"
    >
        <span
            class="inline-flex items-center gap-2 text-sm font-bold transition-transform duration-(--dur) ease-(--spring) {siaga ===
            'tolak'
                ? 'scale-110'
                : 'scale-90'}"
        >
            Tolak<X class="size-5" strokeWidth={2.5} />
        </span>
    </div>

    <article
        bind:this={kartu}
        class="relative flex cursor-grab touch-pan-y flex-col gap-1.5 bg-card px-5 py-4 select-none"
        onpointerdown={turun}
        onpointermove={gerak}
        onpointerup={lepas}
        onpointercancel={lepas}
    >
        {@render children()}
        <div class="mt-2 flex flex-wrap gap-2">
            <Button onclick={() => lempar('setujui')} disabled={terbang}>
                {labelSetujui}
            </Button>
            <Button
                variant="outline"
                onclick={() => lempar('tolak')}
                disabled={terbang}
            >
                Tolak
            </Button>
        </div>
    </article>
</div>
