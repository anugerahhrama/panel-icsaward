import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { JudgingLockAlert } from '@/components/admin/judging-lock-alert';
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
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAppTable } from '@/hooks/table';
import { dashboard } from '@/routes/admin';
import { create, destroy, index } from '@/routes/admin/judges';
import { createColumns, type JudgeRow } from './columns';

type Props = {
    judges: JudgeRow[];
    canManageJudgingSetup: boolean;
};

export default function JudgesIndex({ judges, canManageJudgingSetup }: Props) {
    const locked = !canManageJudgingSetup;
    const [judgeToDelete, setJudgeToDelete] = useState<JudgeRow | null>(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (!judgeToDelete) {
            return;
        }

        setDeleting(true);

        router.delete(destroy(judgeToDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setJudgeToDelete(null),
            onError: () => toast.error('Failed to delete judge.'),
            onFinish: () => setDeleting(false),
        });
    };

    const columns = createColumns({ locked, onDelete: setJudgeToDelete });

    const table = useAppTable({
        columns,
        data: judges,
        initialState: {
            columnPinning: { start: [], end: ['actions'] },
            columnOrder: columns.map((column) => column.id ?? ''),
        },
    });

    return (
        <>
            <Head title="Judges" />

            <table.AppTable>
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <Heading
                            title="Judges"
                            description="Board of Judges profiles, login accounts and category assignments."
                        />
                        <Button asChild>
                            <Link href={create()}>New judge</Link>
                        </Button>
                    </div>

                    <JudgingLockAlert locked={locked} />

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 items-center gap-2">
                            <table.Search placeholder="Search judges..." />
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
                                            {row
                                                .getVisibleCells()
                                                .map((cell) => (
                                                    <TableCell key={cell.id}>
                                                        <table.AppCell
                                                            cell={cell}
                                                        >
                                                            {(c) => (
                                                                <c.FlexRender />
                                                            )}
                                                        </table.AppCell>
                                                    </TableCell>
                                                ))}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={
                                                table.getVisibleLeafColumns()
                                                    .length
                                            }
                                            className="h-24 text-center text-muted-foreground"
                                        >
                                            No judges yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <table.Pagination />
                </div>
            </table.AppTable>

            <Dialog
                open={judgeToDelete !== null}
                onOpenChange={(open) => !open && setJudgeToDelete(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete judge?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <strong>{judgeToDelete?.name}</strong>, their photo and
                        their login account. This action cannot be undone.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={deleting}
                            onClick={confirmDelete}
                        >
                            {deleting && <Spinner />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

JudgesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Judges', href: index() },
    ],
};
