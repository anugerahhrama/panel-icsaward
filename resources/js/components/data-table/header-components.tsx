import { Subscribe } from '@tanstack/react-table';
import { useHeaderContext, useTableContext } from '@/hooks/table-contexts';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

export function SelectAllHeader(): React.ReactNode {
    const table = useTableContext();

    return (
        <Subscribe source={table.atoms.rowSelection}>
            {() => (
                <Checkbox
                    checked={
                        table.getIsAllPageRowsSelected()
                            ? true
                            : table.getIsSomePageRowsSelected()
                              ? 'indeterminate'
                              : false
                    }
                    onCheckedChange={(value) =>
                        table.toggleAllPageRowsSelected(!!value)
                    }
                    aria-label="Select all"
                    className="translate-y-0.5"
                />
            )}
        </Subscribe>
    );
}

export function ResizeHandle(): React.ReactNode {
    const header = useHeaderContext();
    const table = useTableContext();

    if (!header.column.getCanResize()) {
        return null;
    }

    return (
        <Subscribe source={table.atoms.columnResizing}>
            {() => (
                <div
                    onDoubleClick={() => header.column.resetSize()}
                    onMouseDown={header.getResizeHandler()}
                    onTouchStart={header.getResizeHandler()}
                    className={cn(
                        'absolute top-1/2 right-[-2px] z-10 h-6 w-[3px] -translate-y-1/2 cursor-e-resize touch-none rounded-md transition-colors select-none before:absolute before:top-0 before:right-[-4px] before:left-[-4px] before:h-full before:content-[""] hover:bg-blue-600',
                        header.column.getIsResizing() && 'bg-blue-600',
                    )}
                />
            )}
        </Subscribe>
    );
}
