import { Link } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import Heading from '@/components/heading';
import {
    STATUS_LABELS,
    type SubmissionStatus,
} from '@/components/submissions/submission-status-card';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    type Paginated,
    type ServerTableColumns,
    type ServerTableFilters,
    useServerTable,
} from '@/hooks/use-server-table';
import { cn } from '@/lib/utils';
import papers from '@/routes/admin/participants/papers';
import registrations from '@/routes/admin/participants/registrations';

export type ParticipantFilters = ServerTableFilters & {
    category: number | null;
    status: SubmissionStatus | null;
};

export type ParticipantTableProps<TRow> = {
    submissions: Paginated<TRow>;
    filters: ParticipantFilters;
    categories: { id: number; name: string }[];
    statuses: SubmissionStatus[];
};

const ALL = 'all';

const TABS = [
    { key: 'registrations', title: 'Registrations', routes: registrations },
    { key: 'papers', title: 'Paper Submissions', routes: papers },
] as const;

/**
 * Shared shell of the Participants tabs: heading, tab links, filters, Excel export and the server-side table.
 */
export function ParticipantsTable<TRow extends { id: number }>({
    tab,
    description,
    columns,
    defaultSort,
    emptyMessage,
    submissions,
    filters,
    categories,
    statuses,
}: ParticipantTableProps<TRow> & {
    tab: (typeof TABS)[number]['key'];
    description: string;
    columns: ServerTableColumns<TRow>;
    defaultSort: string;
    emptyMessage: string;
}) {
    const routes = TABS.find((item) => item.key === tab)!.routes;

    const { table, setFilter } = useServerTable({
        url: routes.index().url,
        prop: 'submissions',
        columns,
        paginator: submissions,
        filters,
        defaultSort,
    });

    const exportQuery = Object.fromEntries(
        Object.entries({
            search: filters.search,
            category: filters.category,
            status: filters.status,
            sort: filters.sort,
        }).filter(([, value]) => value !== null && value !== ''),
    );

    return (
        <table.AppTable>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading title="Participants" description={description} />
                    <Button variant="outline" asChild>
                        <a href={routes.export({ query: exportQuery }).url}>
                            <FileSpreadsheet />
                            Export Excel
                        </a>
                    </Button>
                </div>

                <nav className="flex gap-1 border-b">
                    {TABS.map((item) => (
                        <Link
                            key={item.key}
                            href={item.routes.index()}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                                item.key === tab
                                    ? 'border-primary text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.title}
                        </Link>
                    ))}
                </nav>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 flex-wrap items-center gap-2">
                        <table.Search placeholder="Search title, name, email..." />
                        <Select
                            value={filters.category?.toString() ?? ALL}
                            onValueChange={(value) =>
                                setFilter(
                                    'category',
                                    value === ALL ? null : Number(value),
                                )
                            }
                        >
                            <SelectTrigger size="sm" className="w-56">
                                <SelectValue placeholder="Category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    All categories
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={category.id.toString()}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.status ?? ALL}
                            onValueChange={(value) =>
                                setFilter(
                                    'status',
                                    value === ALL ? null : value,
                                )
                            }
                        >
                            <SelectTrigger size="sm" className="w-44">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    All statuses
                                </SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {STATUS_LABELS[status]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>
                                                <table.AppCell cell={cell}>
                                                    {(c) => <c.FlexRender />}
                                                </table.AppCell>
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={
                                            table.getVisibleLeafColumns().length
                                        }
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {emptyMessage}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <table.Pagination pageSizeOptions={[10, 20, 50, 100]} />
            </div>
        </table.AppTable>
    );
}
