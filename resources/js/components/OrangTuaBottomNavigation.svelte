<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import FileHeart from 'lucide-svelte/icons/file-heart';
    import House from 'lucide-svelte/icons/house';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes/orang-tua';
    import { index as izinIndex } from '@/routes/orang-tua/izin';

    const items = [
        { label: 'Beranda', href: toUrl(dashboard()), icon: House },
        { label: 'Izin anak', href: toUrl(izinIndex()), icon: FileHeart },
    ];

    const indeksPil = $derived(
        items.findIndex((item) => page.url.split('?')[0] === item.href),
    );
</script>

<nav
    aria-label="Navigasi utama orang tua"
    class="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/90 px-3 pt-1.5 backdrop-blur-xl backdrop-saturate-150"
    style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));"
>
    <div class="relative mx-auto grid max-w-lg grid-cols-2">
        <span
            aria-hidden="true"
            class="pointer-events-none absolute top-1 left-0 flex h-8 w-1/2 justify-center transition-[transform,opacity] duration-(--dur) ease-(--spring) {indeksPil <
            0
                ? 'opacity-0'
                : ''}"
            style="transform: translateX({Math.max(indeksPil, 0) * 100}%);"
        >
            <span class="h-8 w-16 rounded-full bg-accent"></span>
        </span>
        {#each items as item, indeks (item.label)}
            {@const aktif = indeksPil === indeks}
            <Link
                href={item.href}
                aria-current={aktif ? 'page' : undefined}
                class="press relative flex min-h-14 flex-col items-center gap-1 pt-1 text-xs font-bold {aktif
                    ? 'text-accent-foreground'
                    : 'text-muted-foreground hover:text-foreground'}"
            >
                <span class="grid h-8 w-16 place-items-center">
                    <item.icon
                        class="size-5"
                        strokeWidth={aktif ? 2.5 : 2}
                        aria-hidden="true"
                    />
                </span>
                {item.label}
            </Link>
        {/each}
    </div>
</nav>
