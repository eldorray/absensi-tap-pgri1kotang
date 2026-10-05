<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import X from 'lucide-svelte/icons/x';
    import { fade } from 'svelte/transition';
    import { focusTrap } from '@/components/ui/dialog/focus-trap';
    import { kurvaPegas } from '@/lib/pegas';
    import { tarikTutup } from '@/lib/tarikTutup';
    import { cn } from '@/lib/utils';
    import { SHEET_CONTEXT, type SheetContext } from './context';

    let {
        side = 'right',
        class: className = '',
        children,
    }: {
        side?: 'right' | 'left' | 'top' | 'bottom';
        class?: string;
        children?: Snippet;
    } = $props();

    const { open, setOpen, titleId } =
        getContext<SheetContext>(SHEET_CONTEXT);

    const sideClasses: Record<string, string> = {
        right: 'inset-y-0 right-0',
        left: 'inset-y-0 left-0',
        top: 'inset-x-0 top-0',
        bottom: 'inset-x-0 bottom-0',
    };

    const sizeClasses: Record<string, string> = {
        right: 'h-full w-3/4 sm:max-w-sm',
        left: 'h-full w-3/4 sm:max-w-sm',
        top: 'h-auto',
        bottom: 'h-auto',
    };

    const close = () => setOpen(false);

    // Dibaca saat transisi jalan, bukan saat komponen dibuat: komponen ini juga
    // dirender di server (SSR), di mana window tidak ada.
    const reduceMotion = (): boolean =>
        typeof window !== 'undefined' &&
        (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ??
            false);

    // An ancestor with transform/filter/backdrop-filter becomes the containing
    // block for `position: fixed`, so the overlay must live on <body>.
    function portal(node: HTMLElement) {
        document.body.appendChild(node);

        return {
            destroy: () => node.remove(),
        };
    }

    /**
     * Masuk dan keluar lewat sisi yang sama dengan kurva pegas. Keluar mulai
     * dari transform yang sedang tampil, jadi sheet yang ditarik jari lanjut
     * turun dari posisi itu, tidak melompat balik dulu.
     */
    function geser(node: HTMLElement, { keluar }: { keluar: boolean }) {
        const sekarang = getComputedStyle(node).transform;
        const dasar = sekarang === 'none' ? '' : sekarang;
        const mendatar = side === 'left' || side === 'right';
        const ukuran = (mendatar ? node.offsetWidth : node.offsetHeight) + 24;
        const jarak = side === 'left' || side === 'top' ? -ukuran : ukuran;

        return {
            duration: reduceMotion() ? 0 : keluar ? 380 : 520,
            easing: kurvaPegas,
            css: (t: number) =>
                `transform: ${dasar} translate${mendatar ? 'X' : 'Y'}(${(1 - t) * jarak}px)`,
        };
    }
</script>

{#if open()}
    <div class="fixed inset-0 z-50" use:portal>
        <button
            type="button"
            tabindex="-1"
            class="fixed inset-0 border-0 bg-black/40"
            aria-label="Tutup"
            onclick={close}
            transition:fade={{
                duration: reduceMotion() ? 0 : 200,
            }}
        ></button>
        <div
            class={cn(
                'fixed flex flex-col gap-4 overflow-y-auto border-none bg-background p-6 shadow-lg outline-none',
                sideClasses[side] ?? sideClasses.right,
                sizeClasses[side] ?? sizeClasses.right,
                className,
            )}
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            tabindex="-1"
            use:focusTrap={{ onEscape: close }}
            use:tarikTutup={{ aktif: side === 'bottom', onTutup: close }}
            in:geser={{ keluar: false }}
            out:geser={{ keluar: true }}
        >
            <button
                type="button"
                class="ring-offset-background focus-visible:ring-ring absolute top-4 right-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:pointer-events-none"
                aria-label="Tutup"
                onclick={close}
            >
                <X class="size-4" aria-hidden="true" />
            </button>
            {@render children?.()}
        </div>
    </div>
{/if}
