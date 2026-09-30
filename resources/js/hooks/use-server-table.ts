import { router } from '@inertiajs/react';
import type {
    ColumnDef,
    PaginationState,
    RowData,
    SortingState,
    Updater,
} from '@tanstack/react-table';
import { useEffect, useRef, useState } from 'react';
import type { features } from '@/hooks/features';
import { useAppTable } from '@/hooks/table';

/**
 * Laravel's `LengthAwarePaginator` as serialized to an Inertia prop.
 */
export type Paginated<TRow> = {
    data: TRow[];
    current_page: number;
    per_page: number;
    total: number;
};

/**
 * Query string the server validates and echoes back; `sort` is a column id, `-` prefix = descending.
 */
export type ServerTableFilters = {
    search: string | null;
    sort: string;
    per_page: number;
    [filter: string]: string | number | null;
};

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- column value types differ per column
export type ServerTableColumns<TRow extends RowData> = ColumnDef<
    typeof features,
    TRow,
    any
>[];

function resolve<T>(updater: Updater<T>, previous: T): T {
    return typeof updater === 'function'
        ? (updater as (previous: T) => T)(previous)
        : updater;
}

function toSorting(sort: string): SortingState {
    return [{ id: sort.replace(/^-/, ''), desc: sort.startsWith('-') }];
}

/**
 * `useAppTable` in manual mode: pagination, sorting and search are sent to the server as query
 * string parameters, and the table only renders the page the server returned.
 */
export function useServerTable<TRow extends { id: number }>({
    url,
    prop,
    columns,
    paginator,
    filters,
    defaultSort,
}: {
    url: string;
    prop: string;
    columns: ServerTableColumns<TRow>;
    paginator: Paginated<TRow>;
    filters: ServerTableFilters;
    defaultSort: string;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const visit = (
        changes: Partial<ServerTableFilters> & { page?: number },
    ) => {
        const query = { ...filters, page: paginator.current_page, ...changes };

        router.get(
            url,
            Object.fromEntries(
                Object.entries(query).filter(
                    ([key, value]) =>
                        value !== null &&
                        value !== '' &&
                        !(key === 'page' && value === 1) &&
                        !(key === 'sort' && value === defaultSort) &&
                        !(key === 'per_page' && value === 20),
                ),
            ),
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: [prop, 'filters'],
            },
        );
    };

    const visitRef = useRef(visit);
    visitRef.current = visit;

    useEffect(() => {
        if (search === (filters.search ?? '')) {
            return;
        }

        const timeout = setTimeout(
            () => visitRef.current({ search, page: 1 }),
            300,
        );

        return () => clearTimeout(timeout);
    }, [search, filters.search]);

    const pagination: PaginationState = {
        pageIndex: paginator.current_page - 1,
        pageSize: paginator.per_page,
    };
    const sorting = toSorting(filters.sort);

    const table = useAppTable({
        columns,
        data: paginator.data,
        rowCount: paginator.total,
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
        autoResetPageIndex: false,
        enableMultiSort: false,
        enableSortingRemoval: false,
        state: { pagination, sorting, globalFilter: search },
        onGlobalFilterChange: (updater: Updater<string>) =>
            setSearch(resolve(updater, search) ?? ''),
        onPaginationChange: (updater: Updater<PaginationState>) => {
            const next = resolve(updater, pagination);

            visit(
                next.pageSize === pagination.pageSize
                    ? { page: next.pageIndex + 1 }
                    : { per_page: next.pageSize, page: 1 },
            );
        },
        onSortingChange: (updater: Updater<SortingState>) => {
            const [first] = resolve(updater, sorting);

            visit({
                sort: first
                    ? `${first.desc ? '-' : ''}${first.id}`
                    : defaultSort,
                page: 1,
            });
        },
    });

    return {
        table,
        setFilter: (key: string, value: string | number | null) =>
            visit({ [key]: value, page: 1 }),
    };
}
