import { SubmissionStatusBadge } from '@/components/admin/participants/submission-status-badge';
import type { SubmissionStatus } from '@/components/submissions/submission-status-card';
import { createAppColumnHelper } from '@/hooks/table';
import { formatDateTimeWib } from '@/lib/datetime';

export type RegistrationRow = {
    id: number;
    registered_at: string;
    name: string;
    email: string;
    phone: string | null;
    position: string | null;
    company_name: string | null;
    category: string;
    initiative_title: string;
    status: SubmissionStatus;
};

const columnHelper = createAppColumnHelper<RegistrationRow>();

/**
 * Column ids double as the server `sort` keys, so only columns the server can sort by keep sorting enabled.
 */
export const registrationColumns = columnHelper.columns([
    columnHelper.accessor('registered_at', {
        id: 'created_at',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => (
            <span className="whitespace-nowrap">
                {formatDateTimeWib(row.original.registered_at)}
            </span>
        ),
        size: 200,
        meta: { label: 'Registered' },
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
    columnHelper.accessor('phone', {
        id: 'phone',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ cell }) => <cell.TextCell />,
        enableSorting: false,
        size: 150,
        meta: { label: 'Phone' },
    }),
    columnHelper.accessor('company_name', {
        id: 'company_name',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span>{row.original.company_name}</span>
                <span className="text-muted-foreground">
                    {row.original.position}
                </span>
            </div>
        ),
        enableSorting: false,
        size: 220,
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
    columnHelper.accessor('status', {
        id: 'status',
        header: ({ header }) => <header.ColumnHeader />,
        cell: ({ row }) => (
            <SubmissionStatusBadge status={row.original.status} />
        ),
        size: 150,
        meta: { label: 'Status' },
    }),
]);
