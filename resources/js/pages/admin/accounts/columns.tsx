import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { createAppColumnHelper } from '@/hooks/table';

export type AdminRole = 'superadmin' | 'admin';

export const ADMIN_ROLE_LABELS: Record<AdminRole, string> = {
    superadmin: 'Superadmin',
    admin: 'Admin',
};

export type AccountRow = {
    id: number;
    name: string;
    email: string;
    position: string | null;
    phone: string | null;
    role: AdminRole;
    has_account_password: boolean;
    created_at: string;
    deleted_at: string | null;
};

export type RevealedPassword = {
    account_id: number;
    password: string | null;
};

const columnHelper = createAppColumnHelper<AccountRow>();

export function createColumns({
    currentUserId,
    deleted,
    onEdit,
    onDelete,
    onRestore,
}: {
    currentUserId: number;
    deleted: boolean;
    onEdit: (account: AccountRow) => void;
    onDelete: (account: AccountRow) => void;
    onRestore: (account: AccountRow) => void;
}) {
    return columnHelper.columns([
        columnHelper.accessor('name', {
            id: 'name',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <span className="flex items-center gap-2">
                    {row.original.name}
                    {row.original.id === currentUserId && (
                        <Badge variant="secondary">You</Badge>
                    )}
                </span>
            ),
            meta: { label: 'Name', variant: 'text' },
        }),
        columnHelper.accessor('email', {
            id: 'email',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            meta: { label: 'Email', variant: 'text' },
        }),
        columnHelper.accessor('role', {
            id: 'role',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ row }) => (
                <Badge
                    variant={
                        row.original.role === 'superadmin'
                            ? 'default'
                            : 'outline'
                    }
                >
                    {ADMIN_ROLE_LABELS[row.original.role]}
                </Badge>
            ),
            meta: {
                label: 'Role',
                variant: 'select',
                options: Object.entries(ADMIN_ROLE_LABELS).map(
                    ([value, label]) => ({ value, label }),
                ),
            },
        }),
        columnHelper.accessor((row) => row.position ?? '', {
            id: 'position',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            meta: { label: 'Position', variant: 'text' },
        }),
        columnHelper.accessor((row) => row.phone ?? '', {
            id: 'phone',
            header: ({ header }) => <header.ColumnHeader />,
            cell: ({ cell }) => <cell.TextCell />,
            meta: { label: 'Phone', variant: 'text' },
        }),
        columnHelper.accessor(
            (row) => (deleted ? (row.deleted_at ?? '') : row.created_at),
            {
                id: deleted ? 'deleted_at' : 'created_at',
                header: ({ header }) => <header.ColumnHeader />,
                cell: ({ cell }) => <cell.DateCell />,
                meta: {
                    label: deleted ? 'Deleted' : 'Created',
                    variant: 'date',
                },
            },
        ),
        columnHelper.display({
            id: 'actions',
            header: 'Actions',
            enableHiding: false,
            enableResizing: false,
            cell: ({ row }) =>
                deleted ? (
                    <div className="flex justify-end">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onRestore(row.original)}
                        >
                            Restore
                        </Button>
                    </div>
                ) : (
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onEdit(row.original)}
                        >
                            Edit
                        </Button>
                        <DisabledTooltip
                            reason={
                                row.original.id === currentUserId
                                    ? 'You cannot delete your own account.'
                                    : null
                            }
                        >
                            <Button
                                variant="destructive"
                                size="sm"
                                disabled={row.original.id === currentUserId}
                                onClick={() => onDelete(row.original)}
                            >
                                Delete
                            </Button>
                        </DisabledTooltip>
                    </div>
                ),
        }),
    ]);
}
