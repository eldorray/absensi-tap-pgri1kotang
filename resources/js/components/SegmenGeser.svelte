<script lang="ts" generics="T extends string">
    type Opsi = { nilai: T; label: string; jumlah?: number };

    let {
        opsi,
        nilai = $bindable(),
        label,
    }: {
        opsi: Opsi[];
        nilai: T;
        /** Nama grup untuk pembaca layar. */
        label: string;
    } = $props();

    /** Thumb sedang ditahan: mengecil sedikit dan ikut jari. */
    let tahan = $state(false);
    const indeks = $derived(
        Math.max(
            0,
            opsi.findIndex((o) => o.nilai === nilai),
        ),
    );

    function indeksDari(event: PointerEvent): number {
        const kotak = (
            event.currentTarget as HTMLElement
        ).getBoundingClientRect();
        const i = Math.floor(
            (event.clientX - kotak.left - 3) /
                ((kotak.width - 6) / opsi.length),
        );

        return Math.max(0, Math.min(opsi.length - 1, i));
    }

    /**
     * Seperti segmented control iOS: diseret hanya kalau mulai dari segmen
     * yang terpilih. Ketukan di segmen lain cukup lewat onclick tombolnya.
     */
    function turun(event: PointerEvent): void {
        if (event.button !== 0 || indeksDari(event) !== indeks) {
            return;
        }

        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
        tahan = true;
    }

    function gerak(event: PointerEvent): void {
        if (tahan) {
            nilai = opsi[indeksDari(event)].nilai;
        }
    }

    function lepas(): void {
        tahan = false;
    }
</script>

<div
    role="group"
    aria-label={label}
    class="relative grid touch-pan-y rounded-xl bg-muted p-[3px] select-none"
    style="grid-template-columns: repeat({opsi.length}, minmax(0, 1fr));"
    onpointerdown={turun}
    onpointermove={gerak}
    onpointerup={lepas}
    onpointercancel={lepas}
>
    <span
        aria-hidden="true"
        class="pointer-events-none absolute top-[3px] bottom-[3px] left-[3px] rounded-lg bg-card shadow-[var(--g-shadow)] transition-transform duration-(--dur) ease-(--spring) dark:bg-[var(--g-line)]"
        style="width: calc((100% - 6px) / {opsi.length}); transform: translateX({indeks *
            100}%) scale({tahan ? 0.95 : 1});"
    ></span>
    {#each opsi as o (o.nilai)}
        <button
            type="button"
            aria-pressed={o.nilai === nilai}
            onclick={() => (nilai = o.nilai)}
            class="relative flex min-h-10 items-center justify-center gap-1.5 px-2 text-sm font-semibold whitespace-nowrap transition-colors {o.nilai ===
            nilai
                ? 'text-foreground'
                : 'text-muted-foreground hover:text-foreground'}"
        >
            {o.label}
            {#if o.jumlah !== undefined}
                <span class="font-mono text-xs font-normal">{o.jumlah}</span>
            {/if}
        </button>
    {/each}
</div>
