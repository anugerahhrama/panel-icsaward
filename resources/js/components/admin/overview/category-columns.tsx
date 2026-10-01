import { Link } from '@inertiajs/react';
import { ProgressBar } from '@/components/progress-bar';
import { Badge } from '@/components/ui/badge';
import { createAppColumnHelper } from '@/hooks/table';
import { APPLICANT_TYPE_LABELS } from '@/pages/admin/categories/columns';
import registrations from '@/routes/admin/participants/registrations';
import type { OverviewCategory } from './types';

const columnHelper = createAppColumnHelper<OverviewCategory>();

function countColumn(
    key:
        | 'submissions_count'
        | 'papers_count'
        | 'qualified_count'
        | 'judges_count',
    label: string,
) {
    return columnHelper.accessor(key, {
        id: key,
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ cell }) => <cell.TextCell />,
        size: 110,
        meta: { label, variant: 'number' },
    });
}

export const categoryColumns = columnHelper.columns([
    columnHelper.accessor('name', {
        id: 'name',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => (
            <span className="flex items-center gap-2">
                <Link
                    href={registrations.index({
                        query: { category: row.original.id },
                    })}
                    className="font-medium hover:underline"
                >
                    {row.original.name}
                </Link>
                {!row.original.has_assessment_template && (
                    <Badge variant="outline">No template</Badge>
                )}
            </span>
        ),
        meta: { label: 'Category', variant: 'text' },
    }),
    columnHelper.accessor('applicant_type', {
        id: 'applicant_type',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => (
            <Badge variant="outline">
                {APPLICANT_TYPE_LABELS[row.original.applicant_type]}
            </Badge>
        ),
        meta: {
            label: 'Applicant',
            variant: 'select',
            options: Object.entries(APPLICANT_TYPE_LABELS).map(
                ([value, label]) => ({ value, label }),
            ),
        },
    }),
    countColumn('submissions_count', 'Registered'),
    countColumn('papers_count', 'Papers'),
    countColumn('qualified_count', 'Qualified'),
    columnHelper.accessor('judges_count', {
        id: 'judges_count',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) =>
            row.original.judges_count === 0 ? (
                <Badge variant="destructive">None</Badge>
            ) : (
                row.original.judges_count
            ),
        size: 110,
        meta: { label: 'Judges', variant: 'number' },
    }),
    columnHelper.accessor(
        (row) =>
            row.scoring.assigned === 0
                ? -1
                : row.scoring.submitted / row.scoring.assigned,
        {
            id: 'scoring',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => {
                const { assigned, submitted } = row.original.scoring;

                return assigned === 0 ? (
                    <span className="text-muted-foreground">-</span>
                ) : (
                    <div className="grid min-w-32 gap-1">
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {submitted} / {assigned}
                        </span>
                        <ProgressBar
                            value={submitted}
                            max={assigned}
                            className="h-1.5"
                        />
                    </div>
                );
            },
            meta: { label: 'Scoring', variant: 'number' },
        },
    ),
    columnHelper.accessor('finalists_count', {
        id: 'finalists_count',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) =>
            row.original.finalists_confirmed ? (
                <Badge>{row.original.finalists_count} confirmed</Badge>
            ) : (
                <span className="text-muted-foreground">Not confirmed</span>
            ),
        meta: { label: 'Finalists', variant: 'number' },
    }),
    columnHelper.accessor('awards_confirmed', {
        id: 'awards_confirmed',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) =>
            row.original.awards_confirmed ? (
                <Badge>Confirmed</Badge>
            ) : (
                <span className="text-muted-foreground">Not confirmed</span>
            ),
        meta: { label: 'Awards' },
    }),
]);
