export type SheetContext = {
    open: () => boolean;
    setOpen: (value: boolean) => void;
    titleId: string;
};

export const SHEET_CONTEXT = Symbol('sheet');
