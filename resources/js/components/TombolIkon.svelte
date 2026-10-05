<script lang="ts">
    import type { NavIcon } from '@/types';

    type Nada = 'netral' | 'biru' | 'kuning' | 'hijau' | 'merah';

    let {
        ikon,
        label,
        nada = 'netral',
        disabled = false,
        onclick,
    }: {
        ikon: NavIcon;
        /** Dipakai sebagai tooltip sekaligus nama untuk pembaca layar. */
        label: string;
        nada?: Nada;
        disabled?: boolean;
        onclick: () => void;
    } = $props();

    // Saat diam ikon sudah diberi warna tipis sesuai nadanya, supaya aksi
    // merusak terbaca beda dari yang aman walau tanpa hover (layar sentuh).
    // Latar berwarna baru muncul saat hover agar deret ikon tidak ramai.
    const warna: Record<Nada, string> = {
        netral: 'text-muted-foreground hover:bg-muted hover:text-foreground',
        biru: 'text-[var(--g-sky-ink-2)]/85 hover:bg-[var(--g-sky-c)] hover:text-[var(--g-sky-ink-2)]',
        kuning: 'text-[var(--g-yellow-ink-2)]/85 hover:bg-[var(--g-yellow-c)] hover:text-[var(--g-yellow-ink-2)]',
        hijau: 'text-[var(--g-green-ink-2)]/85 hover:bg-[var(--g-green-c)] hover:text-[var(--g-green-ink-2)]',
        merah: 'text-destructive/85 hover:bg-destructive/10 hover:text-destructive',
    };

    const Ikon = $derived(ikon);
</script>

<!-- Area sentuh 44px (size-11), ikonnya tetap size-4. Tooltip teks tampil saat
     fokus keyboard; saat hover cukup title bawaan agar tidak muncul dobel.
     Tooltip menjulur ke kiri-atas supaya tidak menambah area gulir tabel. -->
<button
    type="button"
    {onclick}
    {disabled}
    title={label}
    aria-label={label}
    class="group relative grid size-11 shrink-0 place-items-center rounded-full transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-ring/45 focus-visible:outline-none active:scale-95 disabled:pointer-events-none disabled:opacity-40 motion-reduce:transition-none motion-reduce:active:scale-100 {warna[
        nada
    ]}"
>
    <Ikon class="size-4" />
    <span
        aria-hidden="true"
        class="pointer-events-none absolute right-0 bottom-full z-20 mb-1 hidden rounded-lg bg-foreground px-2 py-1 text-xs font-medium whitespace-nowrap text-background shadow-md group-focus-visible:block"
    >
        {label}
    </span>
</button>
