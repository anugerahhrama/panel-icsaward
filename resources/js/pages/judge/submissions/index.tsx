import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    SCORING_STATUS_LABELS,
    type ScoringStatus,
} from '@/components/judge/scoring-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    type ServerTableFilters,
    useServerTable,
} from '@/hooks/use-server-table';
import { dashboard } from '@/routes/judge';
import { index } from '@/routes/judge/submissions';
import {
    createJudgeSubmissionColumns,
    type JudgeSubmissionRow,
} from './columns';

type Props = {
    submissions: Paginated<JudgeSubmissionRow>;
    filters: ServerTableFilters & {
        category: number | null;
        scoring_status: ScoringStatus | null;
    };
    categories: { id: number; name: string }[];
    stage: { value: string; label: string };
    isScoringOpen: boolean;
};

const ALL = 'all';

const DEFAULT_SORT = 'paper_uploaded_at';

export default function JudgeSubmissions({
    submissions,
    filters,
    categories,
    stage,
    isScoringOpen,
}: Props) {
    const { table, setFilter } = useServerTable({
        url: index().url,
        prop: 'submissions',
        columns: createJudgeSubmissionColumns({ isScoringOpen }),
        paginator: submissions,
        filters,
        defaultSort: DEFAULT_SORT,
    });

    return (
        <>
            <Head title="My Submissions" />
            <table.AppTable>
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <Heading
                            title="My Submissions"
                            description={
                                stage.value === 'pitching'
                                    ? 'Confirmed finalists in the categories assigned to you for pitching.'
                                    : 'Qualified submissions in the categories assigned to you for desk evaluation.'
                            }
                        />
                        <Badge variant="outline">{stage.label}</Badge>
                    </div>

                    {!isScoringOpen && (
                        <Alert>
                            <AlertTitle>Scoring is closed</AlertTitle>
                            <AlertDescription>
                                You can open each submission to read your
                                scores, but they cannot be changed right now.
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 flex-wrap items-center gap-2">
                            <table.Search placeholder="Search title or company..." />
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
                                value={filters.scoring_status ?? ALL}
                                onValueChange={(value) =>
                                    setFilter(
                                        'scoring_status',
                                        value === ALL ? null : value,
                                    )
                                }
                            >
                                <SelectTrigger size="sm" className="w-44">
                                    <SelectValue placeholder="Your scores" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        All statuses
                                    </SelectItem>
                                    {(
                                        Object.keys(
                                            SCORING_STATUS_LABELS,
                                        ) as ScoringStatus[]
                                    ).map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {SCORING_STATUS_LABELS[status]}
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
                                            No submissions match these filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <table.Pagination pageSizeOptions={[10, 20, 50, 100]} />
                </div>
            </table.AppTable>
        </>
    );
}

JudgeSubmissions.layout = {
    breadcrumbs: [
        { title: 'Overview', href: dashboard() },
        { title: 'My Submissions', href: index() },
    ],
};
