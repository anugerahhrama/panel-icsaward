import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAppTable } from '@/hooks/table';
import { categoryColumns } from './category-columns';
import type { OverviewCategory } from './types';

export function CategoryTable({
    categories,
    stageLabel,
}: {
    categories: OverviewCategory[];
    stageLabel: string;
}) {
    const table = useAppTable({
        columns: categoryColumns,
        data: categories,
        initialState: {
            pagination: { pageIndex: 0, pageSize: 50 },
        },
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>Categories</CardTitle>
                <CardDescription>
                    Registrations, judges and results per category. Scoring
                    shows submitted scores in {stageLabel}.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <table.AppTable>
                    <div className="overflow-hidden rounded-md border">
                        <Table>
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers.map((header) => (
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
                </table.AppTable>
            </CardContent>
        </Card>
    );
}
