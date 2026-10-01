import {
    FilePreviewButton,
    type SubmittedFile,
} from '@/components/submissions/file-preview';
import { SubmissionStatusBadge } from '@/components/admin/participants/submission-status-badge';
import type { SubmissionStatus } from '@/components/submissions/submission-status-card';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';
import { formatDateTimeWib } from '@/lib/datetime';

export type PaperRow = {
    id: number;
    uuid: string;
    paper_uploaded_at: string;
    name: string;
    email: string;
    company_name: string | null;
    category: string;
    initiative_title: string;
    paper: SubmittedFile | null;
    statement: SubmittedFile | null;
    status: SubmissionStatus;
    verification: {
        revision_note: string | null;
        revision_deadline: string | null;
        disqualified_reason: string | null;
        reviewed_at: string | null;
        reviewer: string | null;
        notified_at: string | null;
    };
};

const columnHelper = createAppColumnHelper<PaperRow>();

/**
 * Column ids double as the server `sort` keys, so only columns the server can sort by keep sorting enabled.
 */
export function createPaperColumns({
    onReview,
    onPreview,
}: {
    onReview: (submission: PaperRow) => void;
    onPreview: (file: SubmittedFile) => void;
}) {
    return columnHelper.columns([
        columnHelper.accessor('paper_uploaded_at', {
            id: 'paper_uploaded_at',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {formatDateTimeWib(row.original.paper_uploaded_at)}
                </span>
            ),
            size: 200,
            meta: { label: 'Uploaded' },
        }),
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.original.name}</span>
                    <span className="text-muted-foreground">
                        {row.original.email}
                    </span>
                </div>
            ),
            size: 240,
            meta: { label: 'Participant' },
        }),
        columnHelper.accessor('company_name', {
            id: 'company_name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            enableSorting: false,
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
        columnHelper.accessor('initiative_title', {
            id: 'initiative_title',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <p
                    className="max-w-xs truncate"
                    title={row.original.initiative_title}
                >
                    {row.original.initiative_title}
                </p>
            ),
            size: 260,
            meta: { label: 'Initiative' },
        }),
        columnHelper.display({
            id: 'paper',
            header: 'Paper',
            cell: ({ row }) => (
                <FilePreviewButton
                    file={row.original.paper}
                    onPreview={onPreview}
                />
            ),
            size: 200,
        }),
        columnHelper.display({
            id: 'statement',
            header: 'Statement letter',
            cell: ({ row }) => (
                <FilePreviewButton
                    file={row.original.statement}
                    onPreview={onPreview}
                />
            ),
            size: 200,
        }),
        columnHelper.accessor('status', {
            id: 'status',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <SubmissionStatusBadge status={row.original.status} />
            ),
            size: 150,
            meta: { label: 'Status' },
        }),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            enableSorting: false,
            cell: ({ row }) => (
                <Button
                    variant={
                        row.original.status === 'under_review'
                            ? 'default'
                            : 'outline'
                    }
                    size="sm"
                    onClick={() => onReview(row.original)}
                >
                    {row.original.status === 'under_review' ? 'Review' : 'View'}
                </Button>
            ),
            size: 110,
        }),
    ]);
}
