import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import { JUDGING_LOCKED_REASON } from '@/components/admin/judging-lock-alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';

export type ApplicantType = 'organization' | 'individual';

export const APPLICANT_TYPE_LABELS: Record<ApplicantType, string> = {
    organization: 'Organization',
    individual: 'Individual',
};

export type CategoryRow = {
    id: number;
    name: string;
    description: string | null;
    applicant_type: ApplicantType;
    assessment_template_id: number | null;
    assessment_template: { id: number; name: string } | null;
    paper_template_name: string | null;
    paper_template_url: string | null;
    sort_order: number;
    submissions_count: number;
    has_scores: boolean;
};

const columnHelper = createAppColumnHelper<CategoryRow>();

function DeleteButton({
    category,
    locked,
    onDelete,
}: {
    category: CategoryRow;
    locked: boolean;
    onDelete: (category: CategoryRow) => void;
}) {
    const reason = locked
        ? JUDGING_LOCKED_REASON
        : category.submissions_count > 0
          ? 'Categories with submissions cannot be deleted.'
          : null;

    return (
        <DisabledTooltip reason={reason}>
            <Button
                variant="destructive"
                size="sm"
                disabled={reason !== null}
                onClick={() => onDelete(category)}
            >
                Delete
            </Button>
        </DisabledTooltip>
    );
}

export function createColumns({
    locked,
    onEdit,
    onDelete,
}: {
    locked: boolean;
    onEdit: (category: CategoryRow) => void;
    onDelete: (category: CategoryRow) => void;
}) {
    return columnHelper.columns([
        columnHelper.accessor('sort_order', {
            id: 'sort_order',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 90,
            meta: { label: 'Order', variant: 'number' },
        }),
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            meta: { label: 'Name', variant: 'text' },
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
        columnHelper.accessor((row) => row.assessment_template?.name ?? '', {
            id: 'assessment_template',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) =>
                row.original.assessment_template ? (
                    row.original.assessment_template.name
                ) : (
                    <span className="text-muted-foreground">None</span>
                ),
            meta: { label: 'Template', variant: 'text' },
        }),
        columnHelper.accessor((row) => row.paper_template_name ?? '', {
            id: 'paper_template',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) =>
                row.original.paper_template_url ? (
                    <a
                        href={row.original.paper_template_url}
                        download
                        className="inline-block max-w-48 truncate underline underline-offset-4"
                    >
                        {row.original.paper_template_name ?? 'Download'}
                    </a>
                ) : (
                    <span className="text-muted-foreground">Default</span>
                ),
            meta: { label: 'Paper template', variant: 'text' },
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
        columnHelper.accessor('submissions_count', {
            id: 'submissions_count',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            size: 120,
            meta: { label: 'Submissions', variant: 'number' },
        }),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            enableResizing: false,
            cell: ({ row }) => (
                <div className="flex justify-end gap-2">
                    <DisabledTooltip
                        reason={locked ? JUDGING_LOCKED_REASON : null}
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={locked}
                            onClick={() => onEdit(row.original)}
                        >
                            Edit
                        </Button>
                    </DisabledTooltip>
                    <DeleteButton
                        category={row.original}
                        locked={locked}
                        onDelete={onDelete}
                    />
                </div>
            ),
        }),
    ]);
}
