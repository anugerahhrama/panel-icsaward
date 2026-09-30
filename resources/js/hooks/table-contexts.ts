import { createTableHookContexts } from '@tanstack/react-table';
import type { features } from '@/hooks/features';

export const {
    tableContext,
    cellContext,
    headerContext,
    useTableContext,
    useCellContext,
    useHeaderContext,
} = createTableHookContexts<typeof features>();
