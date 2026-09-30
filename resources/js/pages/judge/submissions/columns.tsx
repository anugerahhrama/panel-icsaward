import { Link } from '@inertiajs/react';
import {
    type ScoringStatus,
    ScoringStatusBadge,
} from '@/components/judge/scoring-status-badge';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';
import { formatDateTimeWib } from '@/lib/datetime';
import { show } from '@/routes/judge/submissions';

export type JudgeSubmissionRow = {
    id: number;
    uuid: string;
    initiative_title: string;
    company: string | null;
    category: string;
    paper_uploaded_at: string | null;
    scoring_status: ScoringStatus;
    is_frozen: boolean;
    scored_at: string | null;
};

const columnHelper = createAppColumnHelper<JudgeSubmissionRow>();

/**
 * Column ids double as the server `sort` keys, so only columns the server can sort by keep sorting enabled.
 */
export function createJudgeSubmissionColumns({
    isScoringOpen,
}: {
    isScoringOpen: boolean;
}) {
    return columnHelper.columns([
        columnHelper.accessor('initiative_title', {
            id: 'initiative_title',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <Link
                    href={show(row.original.uuid)}
                    className="block max-w-xs truncate font-medium hover:underline"
                    title={row.original.initiative_title}
                >
                    {row.original.initiative_title}
                </Link>
            ),
            size: 280,
            meta: { label: 'Initiative' },
        }),
        columnHelper.accessor('company', {
            id: 'company',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 200,
            meta: { label: 'Company' },
        }),
        columnHelper.accessor('category', {
            id: 'category',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 220,
            meta: { label: 'Category' },
        }),
        columnHelper.accessor('paper_uploaded_at', {
            id: 'paper_uploaded_at',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {row.original.paper_uploaded_at
                        ? formatDateTimeWib(row.original.paper_uploaded_at)
                        : '—'}
                </span>
            ),
            size: 200,
            meta: { label: 'Paper uploaded' },
        }),
        columnHelper.accessor('scoring_status', {
            id: 'scoring_status',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <ScoringStatusBadge status={row.original.scoring_status} />
            ),
            enableSorting: false,
            size: 130,
            meta: { label: 'Your scores' },
        }),
        columnHelper.accessor('scored_at', {
            id: 'scored_at',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <span className="whitespace-nowrap text-muted-foreground">
                    {row.original.scored_at
                        ? formatDateTimeWib(row.original.scored_at)
                        : '—'}
                </span>
            ),
            size: 200,
            meta: { label: 'Last saved' },
        }),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            enableSorting: false,
            cell: ({ row }) => {
                const canScore =
                    isScoringOpen &&
                    !row.original.is_frozen &&
                    row.original.scoring_status !== 'submitted';

                return (
                    <Button
                        variant={canScore ? 'default' : 'outline'}
                        size="sm"
                        asChild
                    >
                        <Link href={show(row.original.uuid)}>
                            {canScore ? 'Score' : 'View'}
                        </Link>
                    </Button>
                );
            },
            size: 110,
        }),
    ]);
}
