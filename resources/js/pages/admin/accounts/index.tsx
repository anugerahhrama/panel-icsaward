import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AccountForm } from '@/components/admin/accounts/account-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppTable } from '@/hooks/table';
import { dashboard } from '@/routes/admin';
import { destroy, index, restore } from '@/routes/admin/accounts';
import type { Auth } from '@/types';
import { createColumns, type AccountRow } from './columns';

type Props = {
    accounts: AccountRow[];
    deletedAccounts: AccountRow[];
};

type View = 'active' | 'deleted';

type PendingAction = {
    kind: 'delete' | 'restore';
    account: AccountRow;
};

function AccountsTable({
    rows,
    columns,
    emptyMessage,
}: {
    rows: AccountRow[];
    columns: ReturnType<typeof createColumns>;
    emptyMessage: string;
}) {
    const table = useAppTable({
        columns,
        data: rows,
        initialState: {
            columnPinning: { start: [], end: ['actions'] },
            columnOrder: columns.map((column) => column.id ?? ''),
        },
    });

    return (
        <table.AppTable>
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-1 items-center gap-2">
                        <table.Search placeholder="Search accounts..." />
                        <table.FilterList />
                        <table.SortList />
                    </div>
                    <table.ViewOptions />
                </div>

                <div className="overflow-hidden rounded-md border">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers
                                        .filter((header) =>
                                            header.column.getIsVisible(),
                                        )
                                        .map((header) => (
                                            <TableHead key={header.id}>
                                                {header.isPlaceholder ? null : (
                                                    <table.AppHeader
                                                        header={header}
                                                    >
                                                        {(h) => (
                                                            <h.FlexRender />
                                                        )}
                                                    </table.AppHeader>
                                                )}
                                            </TableHead>
                                        ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length ? (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow key={row.id}>
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>
                                                <table.AppCell cell={cell}>
                                                    {(c) => <c.FlexRender />}
                                                </table.AppCell>
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={
                                            table.getVisibleLeafColumns().length
                                        }
                                        className="h-24 text-center text-muted-foreground"
                                    >
                                        {emptyMessage}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <table.Pagination />
            </div>
        </table.AppTable>
    );
}

export default function AccountsIndex({ accounts, deletedAccounts }: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [view, setView] = useState<View>('active');
    const [sheetOpen, setSheetOpen] = useState(false);
    const [accountToEdit, setAccountToEdit] = useState<AccountRow | null>(null);
    const [pendingAction, setPendingAction] = useState<PendingAction | null>(
        null,
    );
    const [processing, setProcessing] = useState(false);

    const confirmPendingAction = () => {
        if (!pendingAction) {
            return;
        }

        setProcessing(true);

        const options = {
            preserveScroll: true,
            onSuccess: () => setPendingAction(null),
            onError: () =>
                toast.error(
                    pendingAction.kind === 'delete'
                        ? 'Failed to delete account.'
                        : 'Failed to restore account.',
                ),
            onFinish: () => setProcessing(false),
        };

        if (pendingAction.kind === 'delete') {
            router.delete(destroy(pendingAction.account.id).url, options);
        } else {
            router.patch(restore(pendingAction.account.id).url, {}, options);
        }
    };

    const deleted = view === 'deleted';
    const columns = createColumns({
        currentUserId: auth.user.id,
        deleted,
        onEdit: (account) => {
            setAccountToEdit(account);
            setSheetOpen(true);
        },
        onDelete: (account) => setPendingAction({ kind: 'delete', account }),
        onRestore: (account) => setPendingAction({ kind: 'restore', account }),
    });

    return (
        <>
            <Head title="Admin Accounts" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Admin Accounts"
                        description="People who can sign in to the admin panel."
                    />
                    <Button
                        onClick={() => {
                            setAccountToEdit(null);
                            setSheetOpen(true);
                        }}
                    >
                        New account
                    </Button>
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    className="w-fit"
                    value={view}
                    onValueChange={(value) => value && setView(value as View)}
                >
                    <ToggleGroupItem value="active" className="px-4">
                        Active ({accounts.length})
                    </ToggleGroupItem>
                    <ToggleGroupItem value="deleted" className="px-4">
                        Deleted ({deletedAccounts.length})
                    </ToggleGroupItem>
                </ToggleGroup>

                <AccountsTable
                    key={view}
                    rows={deleted ? deletedAccounts : accounts}
                    columns={columns}
                    emptyMessage={
                        deleted ? 'No deleted accounts.' : 'No accounts yet.'
                    }
                />
            </div>

            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {accountToEdit ? 'Edit account' : 'New account'}
                        </SheetTitle>
                        <SheetDescription>
                            {accountToEdit
                                ? 'Update the account details or reset its password.'
                                : 'The account can sign in right away, without email verification.'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-4">
                        <AccountForm
                            key={accountToEdit?.id ?? 'new'}
                            account={accountToEdit ?? undefined}
                            isSelf={accountToEdit?.id === auth.user.id}
                            onSaved={() => setSheetOpen(false)}
                        />
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {pendingAction?.kind === 'restore'
                            ? 'Restore account?'
                            : 'Delete account?'}
                    </DialogTitle>
                    <DialogDescription>
                        {pendingAction?.kind === 'restore' ? (
                            <>
                                <strong>{pendingAction.account.name}</strong>{' '}
                                will be able to sign in again with their
                                previous password.
                            </>
                        ) : (
                            <>
                                <strong>{pendingAction?.account.name}</strong>{' '}
                                will no longer be able to sign in. You can
                                restore the account later from the Deleted tab.
                            </>
                        )}
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant={
                                pendingAction?.kind === 'restore'
                                    ? 'default'
                                    : 'destructive'
                            }
                            disabled={processing}
                            onClick={confirmPendingAction}
                        >
                            {processing && <Spinner />}
                            {pendingAction?.kind === 'restore'
                                ? 'Restore'
                                : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AccountsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Admin Accounts', href: index() },
    ],
};
