import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import {
    JUDGING_LOCKED_REASON,
    JudgingLockAlert,
} from '@/components/admin/judging-lock-alert';
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
import { create, destroy, index } from '@/routes/admin/assessment-templates';
import { createColumns, type TemplateRow } from './columns';

type Props = {
    templates: TemplateRow[];
    canManageJudgingSetup: boolean;
};

export default function AssessmentTemplatesIndex({
    templates,
    canManageJudgingSetup,
}: Props) {
    const locked = !canManageJudgingSetup;
    const [templateToDelete, setTemplateToDelete] =
        useState<TemplateRow | null>(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (!templateToDelete) {
            return;
        }

        setDeleting(true);

        router.delete(destroy(templateToDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setTemplateToDelete(null),
            onError: () => toast.error('Failed to delete template.'),
            onFinish: () => setDeleting(false),
        });
    };

    const columns = createColumns({ locked, onDelete: setTemplateToDelete });

    const table = useAppTable({
        columns,
        data: templates,
        initialState: {
            columnPinning: { start: [], end: ['actions'] },
            columnOrder: columns.map((column) => column.id ?? ''),
        },
    });

    return (
        <>
            <Head title="Assessment Templates" />

            <table.AppTable>
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <Heading
                            title="Assessment Templates"
                            description="Scoring rubrics judges use to assess submissions in each category."
                        />
                        {locked ? (
                            <DisabledTooltip reason={JUDGING_LOCKED_REASON}>
                                <Button disabled>New template</Button>
                            </DisabledTooltip>
                        ) : (
                            <Button asChild>
                                <Link href={create()}>New template</Link>
                            </Button>
                        )}
                    </div>

                    <JudgingLockAlert locked={locked} />

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 items-center gap-2">
                            <table.Search placeholder="Search templates..." />
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
                                            No assessment templates yet.
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
                open={templateToDelete !== null}
                onOpenChange={(open) => !open && setTemplateToDelete(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete template?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <strong>{templateToDelete?.name}</strong> and its
                        scoring criteria. This action cannot be undone.
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

AssessmentTemplatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Assessment Templates', href: index() },
    ],
};
