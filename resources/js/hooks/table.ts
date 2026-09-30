import { createTableHook } from '@tanstack/react-table';
import { features } from '@/hooks/features';
import { dynamicFilterFn } from '@/lib/data-table';
import {
    tableContext,
    cellContext,
    headerContext,
    useTableContext,
    useCellContext,
    useHeaderContext,
} from '@/hooks/table-contexts';

import { DataTableSearch } from '@/components/data-table/data-table-search';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableFilterList } from '@/components/data-table/data-table-filter-list';
import { DataTableSortList } from '@/components/data-table/data-table-sort-list';
import { DataTableViewOptions } from '@/components/data-table/data-table-view-options';

import {
    GroupedCell,
    SelectCell,
    TextCell,
    DateCell,
} from '@/components/data-table/cell-components';

import { ColumnHeader } from '@/components/data-table/data-table-column-header';
import {
    ResizeHandle,
    SelectAllHeader,
} from '@/components/data-table/header-components';

export {
    tableContext,
    cellContext,
    headerContext,
    useTableContext,
    useCellContext,
    useHeaderContext,
};

export const { createAppColumnHelper, useAppTable } = createTableHook({
    features,
    tableContext,
    cellContext,
    headerContext,
    defaultColumn: {
        size: 120,
        minSize: 60,
        maxSize: 800,
        filterFn: dynamicFilterFn,
    },
    globalFilterFn: 'fuzzy',
    getRowId: (row: { id: string | number }) => String(row.id),
    enableRowSelection: true,
    columnResizeMode: 'onChange' as const,

    tableComponents: {
        Search: DataTableSearch,
        Pagination: DataTablePagination,
        FilterList: DataTableFilterList,
        SortList: DataTableSortList,
        ViewOptions: DataTableViewOptions,
    },

    cellComponents: {
        SelectCell,
        TextCell,
        DateCell,
        GroupedCell,
    },

    headerComponents: {
        ColumnHeader,
        SelectAllHeader,
        ResizeHandle,
    },
});
