import { Link } from '@inertiajs/react';
import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import { JUDGING_LOCKED_REASON } from '@/components/admin/judging-lock-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';
import { edit } from '@/routes/admin/assessment-templates';

export const TOTAL_WEIGHT = 100;

export type TemplateRow = {
    id: number;
    name: string;
    description: string | null;
    criteria_count: number;
    criteria_sum_weight: number | null;
    categories_count: number;
    has_scores: boolean;
};

const columnHelper = createAppColumnHelper<TemplateRow>();

function DeleteButton({
    template,
    locked,
    onDelete,
}: {
    template: TemplateRow;
    locked: boolean;
    onDelete: (template: TemplateRow) => void;
}) {
    let reason: string | null = null;

    if (locked) {
        reason = JUDGING_LOCKED_REASON;
    } else if (template.categories_count > 0) {
        reason = 'Templates used by a category cannot be deleted.';
    } else if (template.has_scores) {
        reason = 'Templates with judge scores cannot be deleted.';
    }

    return (
        <DisabledTooltip reason={reason}>
            <Button
                variant="destructive"
                size="sm"
                disabled={reason !== null}
                onClick={() => onDelete(template)}
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
    onDelete: (template: TemplateRow) => void;
}) {
    return columnHelper.columns([
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            meta: { label: 'Name', variant: 'text' },
        }),
        columnHelper.accessor('description', {
            id: 'description',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <p className="max-w-md truncate text-muted-foreground">
                    {row.original.description}
                </p>
            ),
            meta: { label: 'Description', variant: 'text' },
        }),
        columnHelper.accessor('criteria_count', {
            id: 'criteria_count',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 110,
            meta: { label: 'Aspects', variant: 'number' },
        }),
        columnHelper.accessor('criteria_sum_weight', {
            id: 'criteria_sum_weight',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => {
                const total = row.original.criteria_sum_weight ?? 0;

                return (
                    <Badge
                        variant={
                            total === TOTAL_WEIGHT ? 'outline' : 'destructive'
                        }
                    >
                        {total}%
                    </Badge>
                );
            },
            size: 130,
            meta: { label: 'Total weight', variant: 'number' },
        }),
        columnHelper.accessor('categories_count', {
            id: 'categories_count',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 120,
            meta: { label: 'Categories', variant: 'number' },
        }),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            enableResizing: false,
            cell: ({ row }) => (
                <div className="flex justify-end gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={edit(row.original.id)}>
                            {locked ? 'View' : 'Edit'}
                        </Link>
                    </Button>
                    <DeleteButton
                        template={row.original}
                        locked={locked}
                        onDelete={onDelete}
                    />
                </div>
            ),
        }),
    ]);
}
