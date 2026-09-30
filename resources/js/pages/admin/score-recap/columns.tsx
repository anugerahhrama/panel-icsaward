import {
    type Award,
    AwardBadge,
} from '@/components/admin/score-recap/award-badge';
import { Badge } from '@/components/ui/badge';
import { createAppColumnHelper } from '@/hooks/table';

export type ScoreRecapRow = {
    id: number;
    rank: number | null;
    name: string;
    email: string;
    company_name: string | null;
    category: string;
    initiative_title: string;
    judges_submitted: number | null;
    judges_assigned: number | null;
    raw_score: number | null;
    score: number | null;
    stage1_score: number | null;
    stage2_score: number | null;
    award: Award | null;
    is_finalist: boolean;
};

export type ScoreRecapStage = 'desk_evaluation' | 'pitching' | 'final';

const columnHelper = createAppColumnHelper<ScoreRecapRow>();

function formatScore(value: number | null): string {
    return value === null ? '—' : value.toFixed(2);
}

function referenceScoreColumn(
    key: 'stage1_score' | 'stage2_score',
    label: string,
) {
    return columnHelper.accessor(key, {
        id: key,
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {formatScore(row.original[key])}
            </span>
        ),
        enableSorting: false,
        size: 120,
        meta: { label },
    });
}

/**
 * Column ids double as the server `sort` keys, so only columns the server can sort by keep sorting enabled.
 * Stage 2 and Final rows are all finalists, so the badge is left out there and the earlier stage scores are shown for
 * reference. Final has no raw score or judge counts of its own.
 */
export function createScoreRecapColumns({ stage }: { stage: ScoreRecapStage }) {
    const isStageOne = stage === 'desk_evaluation';
    const isFinal = stage === 'final';

    const judgesColumn = columnHelper.accessor('judges_submitted', {
        id: 'judges',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => {
            const { judges_submitted: submitted, judges_assigned: assigned } =
                row.original;

            if (submitted === null || assigned === null) {
                return <span className="text-muted-foreground">—</span>;
            }

            return (
                <div className="flex items-center gap-2 whitespace-nowrap">
                    <span className="tabular-nums">
                        {submitted}/{assigned}
                    </span>
                    {submitted < assigned && (
                        <Badge variant="outline">Incomplete</Badge>
                    )}
                </div>
            );
        },
        enableSorting: false,
        size: 150,
        meta: { label: 'Judges' },
    });

    const rawScoreColumn = columnHelper.accessor('raw_score', {
        id: 'raw_score',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => (
            <span className="tabular-nums">
                {formatScore(row.original.raw_score)}
            </span>
        ),
        size: 120,
        meta: { label: 'Raw score' },
    });

    return columnHelper.columns([
        columnHelper.accessor('rank', {
            id: 'rank',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <span className="font-semibold tabular-nums">
                    {row.original.rank ?? '—'}
                </span>
            ),
            size: 90,
            meta: { label: 'Rank' },
        }),
        columnHelper.accessor('category', {
            id: 'category',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 220,
            meta: { label: 'Category' },
        }),
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="flex items-center gap-2 font-medium">
                        {row.original.name}
                        {row.original.is_finalist && isStageOne && (
                            <Badge>Finalist</Badge>
                        )}
                    </span>
                    <span className="text-muted-foreground">
                        {row.original.company_name ?? row.original.email}
                    </span>
                </div>
            ),
            size: 240,
            meta: { label: 'Participant' },
        }),
        columnHelper.accessor('initiative_title', {
            id: 'initiative_title',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 260,
            meta: { label: 'Initiative' },
        }),
        ...(isFinal
            ? [
                  referenceScoreColumn('stage1_score', 'Stage 1'),
                  referenceScoreColumn('stage2_score', 'Stage 2'),
              ]
            : [
                  judgesColumn,
                  ...(isStageOne
                      ? []
                      : [referenceScoreColumn('stage1_score', 'Stage 1')]),
                  rawScoreColumn,
              ]),
        columnHelper.accessor('score', {
            id: 'score',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <span className="font-medium tabular-nums">
                    {formatScore(row.original.score)}
                </span>
            ),
            size: 130,
            meta: { label: isFinal ? 'Final score' : 'Normalized' },
        }),
        ...(isFinal
            ? [
                  columnHelper.accessor('award', {
                      id: 'award',
                      header: ({ header }) => <header.ColumnHeader />,
                      cell: ({ row }) =>
                          row.original.award ? (
                              <AwardBadge award={row.original.award} />
                          ) : (
                              <span className="text-muted-foreground">—</span>
                          ),
                      enableSorting: false,
                      size: 120,
                      meta: { label: 'Award' },
                  }),
              ]
            : []),
    ]);
}
