import * as React from 'react';
import { Search } from 'lucide-react';
import { Subscribe } from '@tanstack/react-table';
import { useTableContext } from '@/hooks/table-contexts';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export function DataTableSearch({
    placeholder = 'Search...',
    className,
}: {
    placeholder?: string;
    className?: string;
}) {
    const table = useTableContext();

    return (
        <Subscribe
            source={table.store}
            selector={(s) => (s.globalFilter as string) ?? ''}
        >
            {(globalFilter) => (
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder={placeholder}
                        value={globalFilter}
                        onChange={(e) => table.setGlobalFilter(e.target.value)}
                        className={cn('h-8 w-48 pl-8 lg:w-64', className)}
                    />
                </div>
            )}
        </Subscribe>
    );
}
