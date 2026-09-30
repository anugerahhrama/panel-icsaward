import { Head } from '@inertiajs/react';
import { JudgingLockAlert } from '@/components/admin/judging-lock-alert';
import Heading from '@/components/heading';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAppTable } from '@/hooks/table';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/pitching';
import { createColumns, type PitchingCategoryRow } from './columns';

type Props = {
    categories: PitchingCategoryRow[];
    canManageJudgingSetup: boolean;
};

export default function PitchingIndex({
    categories,
    canManageJudgingSetup,
}: Props) {
    const locked = !canManageJudgingSetup;
    const columns = createColumns({ locked });

    const table = useAppTable({
        columns,
        data: categories,
        initialState: {
            columnPinning: { start: [], end: ['actions'] },
            columnOrder: columns.map((column) => column.id ?? ''),
        },
    });

    return (
        <>
            <Head title="Pitching" />

            <table.AppTable>
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <Heading
                        title="Pitching"
                        description="Schedule each category's pitching session and the time slot of every finalist."
                    />

                    <JudgingLockAlert locked={locked} />

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 items-center gap-2">
                            <table.Search placeholder="Search categories..." />
                            <table.FilterList />
                            <table.SortList />
                        </div>
                        <table.ViewOptions />
                    </div>

                    <div className="overflow-hidden rounded-md border">
                        <Table>
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers
                                            .filter((header) =>
                                                header.column.getIsVisible(),
                                            )
                                            .map((header) => (
                                                <TableHead key={header.id}>
                                                    {header.isPlaceholder ? null : (
                                                        <table.AppHeader
                                                            header={header}
                                                        >
                                                            {(h) => (
                                                                <h.FlexRender />
                                                            )}
                                                        </table.AppHeader>
                                                    )}
                                                </TableHead>
                                            ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows.length ? (
                                    table.getRowModel().rows.map((row) => (
                                        <TableRow key={row.id}>
                                            {row
                                                .getVisibleCells()
                                                .map((cell) => (
                                                    <TableCell key={cell.id}>
                                                        <table.AppCell
                                                            cell={cell}
                                                        >
                                                            {(c) => (
                                                                <c.FlexRender />
                                                            )}
                                                        </table.AppCell>
                                                    </TableCell>
                                                ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={
                                                table.getVisibleLeafColumns()
                                                    .length
                                            }
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            No categories yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <table.Pagination />
                </div>
            </table.AppTable>
        </>
    );
}

PitchingIndex.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Pitching', href: index() },
    ],
};
