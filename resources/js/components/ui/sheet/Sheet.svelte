<script lang="ts">
    import type { Snippet } from 'svelte';
    import { setContext } from 'svelte';
    import { SHEET_CONTEXT, type SheetContext } from './context';

    let {
        open = $bindable(false),
        onOpenChange,
        children,
    }: {
        open?: boolean;
        onOpenChange?: (open: boolean) => void;
        children?: Snippet;
    } = $props();

    const uid = $props.id();

    const context: SheetContext = {
        open: () => open,
        titleId: `sheet-judul-${uid}`,
        setOpen: (value: boolean) => {
            open = value;
            onOpenChange?.(value);
        },
    };

    setContext(SHEET_CONTEXT, context);
</script>

{@render children?.()}
