<script lang="ts">
    import { page } from '@inertiajs/svelte';

    let {
        class: className = '',
        gelap = false,
    }: {
        class?: string;
        /** Di atas sidebar gelap: warna abu teks biasa hanya 2.5:1 di sana. */
        gelap?: boolean;
    } = $props();

    const versi = $derived(page.props.versi);
</script>

<p
    class="px-2 py-1 text-center text-[0.6875rem] {gelap
        ? 'text-sidebar-foreground/70'
        : 'text-muted-foreground'} {className}"
>
    v{versi} &middot;
    <a
        href="https://fahmiealkhudhorie.site/"
        target="_blank"
        rel="noopener noreferrer"
        class="font-medium underline-offset-2 hover:underline {gelap
            ? 'hover:text-sidebar-foreground'
            : 'hover:text-foreground'}"
    >
        Crafted by F.A.K
    </a>
</p>
