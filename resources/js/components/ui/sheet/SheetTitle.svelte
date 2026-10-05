<script lang="ts">
    import type { Snippet } from 'svelte';
    import { getContext } from 'svelte';
    import { cn } from '@/lib/utils';
    import { SHEET_CONTEXT, type SheetContext } from './context';

    let {
        class: className = '',
        children,
    }: {
        class?: string;
        children?: Snippet;
    } = $props();

    const context = getContext<SheetContext | undefined>(SHEET_CONTEXT);
</script>

<h2
    id={context?.titleId}
    class={cn('text-lg font-semibold text-foreground', className)}
>
    {@render children?.()}
</h2>
