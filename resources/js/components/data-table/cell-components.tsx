import { ChevronDown, ChevronRight } from 'lucide-react';
import { FlexRender, Subscribe } from '@tanstack/react-table';
import { useCellContext, useTableContext } from '@/hooks/table-contexts';
import { Checkbox } from '@/components/ui/checkbox';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/utils';

export function SelectCell(): React.ReactNode {
    const cell = useCellContext();
    const table = useTableContext();
    const row = cell.row;

    return (
        <Subscribe source={table.atoms.rowSelection}>
            {() => (
                <Checkbox
                    checked={
                        row.getIsSelected()
                            ? true
                            : row.getIsSomeSelected()
                              ? 'indeterminate'
                              : false
                    }
                    onCheckedChange={(value) => row.toggleSelected(!!value)}
                    aria-label="Select row"
                    className="translate-y-0.5"
                />
            )}
        </Subscribe>
    );
}

export function TextCell(): React.ReactNode {
    const cell = useCellContext<string | undefined>();
    const value = cell.getValue();
    return value == null ? null : <>{String(value)}</>;
}

export function DateCell(): React.ReactNode {
    const cell = useCellContext<string>();
    return <>{formatDate(cell.getValue())}</>;
}

export function GroupedCell(): React.ReactNode {
    const cell = useCellContext();
    const table = useTableContext();
    const row = cell.row;

    return (
        <Subscribe source={table.atoms.expanded}>
            {() => (
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-2 h-7 gap-1 px-2"
                    onClick={row.getToggleExpandedHandler()}
                    disabled={!row.getCanExpand()}
                    style={{
                        paddingLeft: `calc(${row.depth} * 1.5rem + 0.5rem)`,
                    }}
                >
                    {row.getIsExpanded() ? (
                        <ChevronDown className="size-4" />
                    ) : (
                        <ChevronRight className="size-4" />
                    )}
                    <FlexRender cell={cell} />
                    <span className="text-muted-foreground">
                        ({row.subRows.length})
                    </span>
                </Button>
            )}
        </Subscribe>
    );
}
