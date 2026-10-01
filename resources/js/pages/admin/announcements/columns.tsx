import { CircleCheck, Megaphone } from 'lucide-react';
import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';
import { formatDateTimeWib } from '@/lib/datetime';

export type AnnouncementType = 'finalists' | 'invitations' | 'winners';

export type AnnouncementState = {
    announced_at: string | null;
    blocked_reason: string | null;
    recipients_count: number;
    sent_count: number;
};

export type AnnouncementCategoryRow = {
    id: number;
    name: string;
    finalists_count: number;
    announcements: Record<AnnouncementType, AnnouncementState>;
};

/**
 * Matches `App\Enums\Announcement::label()`.
 */
export const ANNOUNCEMENT_LABELS: Record<AnnouncementType, string> = {
    finalists: 'Finalists',
    invitations: 'Awarding Night invitations',
    winners: 'Winners',
};

const ACTION_LABELS: Record<AnnouncementType, string> = {
    finalists: 'Announce',
    invitations: 'Send invitations',
    winners: 'Announce',
};

const columnHelper = createAppColumnHelper<AnnouncementCategoryRow>();

function AnnouncementCell({
    state,
    type,
    onAnnounce,
}: {
    state: AnnouncementState;
    type: AnnouncementType;
    onAnnounce: () => void;
}) {
    if (state.announced_at !== null) {
        const pending = state.recipients_count - state.sent_count;

        return (
            <div className="flex flex-col items-start gap-1 text-sm">
                <Badge>
                    <CircleCheck />
                    {type === 'invitations' ? 'Sent' : 'Announced'}
                </Badge>
                <span className="text-muted-foreground">
                    {formatDateTimeWib(state.announced_at)}
                </span>
                <span className="text-muted-foreground">
                    {state.sent_count} / {state.recipients_count} emails sent
                    {pending > 0 && ' (queued)'}
                </span>
            </div>
        );
    }

    return (
        <DisabledTooltip reason={state.blocked_reason}>
            <Button
                size="sm"
                variant="outline"
                disabled={state.blocked_reason !== null}
                onClick={onAnnounce}
            >
                <Megaphone />
                {ACTION_LABELS[type]}
            </Button>
        </DisabledTooltip>
    );
}

export function createColumns({
    onAnnounce,
}: {
    onAnnounce: (row: AnnouncementCategoryRow, type: AnnouncementType) => void;
}) {
    const announcementColumn = (type: AnnouncementType) =>
        columnHelper.accessor(
            (row) => row.announcements[type].announced_at ?? '',
            {
                id: type,
                header: ({ header }) => <header.ColumnHeader />,
                cell: ({ row }) => (
                    <AnnouncementCell
                        state={row.original.announcements[type]}
                        type={type}
                        onAnnounce={() => onAnnounce(row.original, type)}
                    />
                ),
                enableSorting: false,
                size: 200,
                meta: { label: ANNOUNCEMENT_LABELS[type], variant: 'text' },
            },
        );

    return columnHelper.columns([
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            meta: { label: 'Category', variant: 'text' },
        }),
        columnHelper.accessor('finalists_count', {
            id: 'finalists_count',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 100,
            meta: { label: 'Finalists', variant: 'number' },
        }),
        announcementColumn('finalists'),
        announcementColumn('invitations'),
        announcementColumn('winners'),
    ]);
}
