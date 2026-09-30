import { Link } from '@inertiajs/react';
import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';
import { formatDateTimeWib } from '@/lib/datetime';
import { edit } from '@/routes/admin/pitching';

export type PitchingCategoryRow = {
    id: number;
    name: string;
    finalists_count: number;
    finalists_confirmed_at: string | null;
    session: {
        scheduled_at: string;
        location: string | null;
        meeting_link: string | null;
        slots_count: number;
    } | null;
};

const columnHelper = createAppColumnHelper<PitchingCategoryRow>();

function ScheduleButton({
    category,
    locked,
}: {
    category: PitchingCategoryRow;
    locked: boolean;
}) {
    const reason =
        category.finalists_confirmed_at === null
            ? 'Confirm the finalists in Score Recap first.'
            : null;

    return (
        <DisabledTooltip reason={reason}>
            <Button
                variant="outline"
                size="sm"
                disabled={reason !== null}
                asChild={reason === null}
            >
                {reason === null ? (
                    <Link href={edit(category.id)}>
                        {locked ? 'View' : 'Schedule'}
                    </Link>
                ) : (
                    'Schedule'
                )}
            </Button>
        </DisabledTooltip>
    );
}

export function createColumns({ locked }: { locked: boolean }) {
    return columnHelper.columns([
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            meta: { label: 'Category', variant: 'text' },
        }),
        columnHelper.accessor('finalists_count', {
            id: 'finalists',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) =>
                row.original.finalists_confirmed_at === null ? (
                    <Badge variant="outline">Not confirmed</Badge>
                ) : (
                    `${row.original.finalists_count} confirmed`
                ),
            size: 140,
            meta: { label: 'Finalists', variant: 'number' },
        }),
        columnHelper.accessor((row) => row.session?.scheduled_at ?? '', {
            id: 'scheduled_at',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) =>
                row.original.session ? (
                    formatDateTimeWib(row.original.session.scheduled_at)
                ) : (
                    <span className="text-muted-foreground">Not scheduled</span>
                ),
            meta: { label: 'Session', variant: 'text' },
        }),
        columnHelper.accessor((row) => row.session?.location ?? '', {
            id: 'location',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => {
                const session = row.original.session;

                if (!session) {
                    return <span className="text-muted-foreground">—</span>;
                }

                return (
                    <div className="flex max-w-64 flex-col">
                        {session.location && (
                            <span className="truncate">{session.location}</span>
                        )}
                        {session.meeting_link && (
                            <a
                                href={session.meeting_link}
                                target="_blank"
                                rel="noreferrer"
                                className="truncate text-muted-foreground underline underline-offset-4"
                            >
                                {session.meeting_link}
                            </a>
                        )}
                    </div>
                );
            },
            meta: { label: 'Location', variant: 'text' },
        }),
        columnHelper.accessor((row) => row.session?.slots_count ?? 0, {
            id: 'slots',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) =>
                row.original.session
                    ? `${row.original.session.slots_count} / ${row.original.finalists_count}`
                    : '—',
            size: 100,
            meta: { label: 'Slots', variant: 'number' },
        }),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            enableResizing: false,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <ScheduleButton category={row.original} locked={locked} />
                </div>
            ),
        }),
    ]);
}
