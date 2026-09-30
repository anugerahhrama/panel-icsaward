import { Link } from '@inertiajs/react';
import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import { JUDGING_LOCKED_REASON } from '@/components/admin/judging-lock-alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/admin/judges';

export type JudgeRow = {
    id: number;
    name: string;
    position: string;
    institution: string | null;
    photo_url: string | null;
    account_email: string | null;
    show_on_landing: boolean;
    landing_category: string | null;
    sort_order: number;
    categories_count: number;
    recused_categories_count: number;
    has_scores: boolean;
};

const columnHelper = createAppColumnHelper<JudgeRow>();

function NameCell({ judge }: { judge: JudgeRow }) {
    const getInitials = useInitials();

    return (
        <div className="flex items-center gap-3">
            <Avatar className="size-9">
                {judge.photo_url && (
                    <AvatarImage src={judge.photo_url} alt={judge.name} />
                )}
                <AvatarFallback>{getInitials(judge.name)}</AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <p className="truncate font-medium">{judge.name}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {[judge.position, judge.institution]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>
        </div>
    );
}

function DeleteButton({
    judge,
    locked,
    onDelete,
}: {
    judge: JudgeRow;
    locked: boolean;
    onDelete: (judge: JudgeRow) => void;
}) {
    let reason: string | null = null;

    if (locked) {
        reason = JUDGING_LOCKED_REASON;
    } else if (judge.has_scores) {
        reason = 'Judges who have given scores cannot be deleted.';
    } else if (judge.categories_count > 0) {
        reason = "Remove the judge's category assignments before deleting.";
    }

    return (
        <DisabledTooltip reason={reason}>
            <Button
                variant="destructive"
                size="sm"
                disabled={reason !== null}
                onClick={() => onDelete(judge)}
            >
                Delete
            </Button>
        </DisabledTooltip>
    );
}

export function createColumns({
    locked,
    onDelete,
}: {
    locked: boolean;
    onDelete: (judge: JudgeRow) => void;
}) {
    return columnHelper.columns([
        columnHelper.accessor('sort_order', {
            id: 'sort_order',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 80,
            meta: { label: 'Order', variant: 'number' },
        }),
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => <NameCell judge={row.original} />,
            size: 320,
            meta: { label: 'Judge', variant: 'text' },
        }),
        columnHelper.accessor('account_email', {
            id: 'account_email',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) =>
                row.original.account_email ? (
                    <span className="text-sm">
                        {row.original.account_email}
                    </span>
                ) : (
                    <Badge variant="outline">No account</Badge>
                ),
            meta: { label: 'Login account', variant: 'text' },
        }),
        columnHelper.accessor('categories_count', {
            id: 'categories_count',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <span className="text-sm tabular-nums">
                    {row.original.categories_count} assigned
                    {row.original.recused_categories_count > 0 && (
                        <span className="text-muted-foreground">
                            {' '}
                            · {row.original.recused_categories_count} recused
                        </span>
                    )}
                </span>
            ),
            size: 170,
            meta: { label: 'Categories', variant: 'number' },
        }),
        columnHelper.accessor('show_on_landing', {
            id: 'show_on_landing',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) =>
                row.original.show_on_landing ? (
                    <Badge variant="secondary">
                        {row.original.landing_category ?? 'Shown'}
                    </Badge>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        Hidden
                    </span>
                ),
            meta: { label: 'Landing page', variant: 'boolean' },
        }),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            enableResizing: false,
            cell: ({ row }) => (
                <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={edit(row.original.id)}>Edit</Link>
                    </Button>
                    <DeleteButton
                        judge={row.original}
                        locked={locked}
                        onDelete={onDelete}
                    />
                </div>
            ),
        }),
    ]);
}
