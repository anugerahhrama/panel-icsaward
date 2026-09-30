import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    CategoryForm,
    type TemplateOption,
} from '@/components/admin/categories/category-form';
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
import { useAppTable } from '@/hooks/table';
import { dashboard } from '@/routes/admin';
import { destroy, index } from '@/routes/admin/categories';
import { createColumns, type CategoryRow } from './columns';

type Props = {
    categories: CategoryRow[];
    templates: TemplateOption[];
    canManageJudgingSetup: boolean;
};

export default function CategoriesIndex({
    categories,
    templates,
    canManageJudgingSetup,
}: Props) {
    const locked = !canManageJudgingSetup;
    const [sheetOpen, setSheetOpen] = useState(false);
    const [categoryToEdit, setCategoryToEdit] = useState<CategoryRow | null>(
        null,
    );
    const [categoryToDelete, setCategoryToDelete] =
        useState<CategoryRow | null>(null);
    const [deleting, setDeleting] = useState(false);

    const nextSortOrder =
        Math.max(0, ...categories.map((category) => category.sort_order)) + 1;

    const confirmDelete = () => {
        if (!categoryToDelete) {
            return;
        }

        setDeleting(true);

        router.delete(destroy(categoryToDelete.id).url, {
            preserveScroll: true,
            onSuccess: () => setCategoryToDelete(null),
            onError: () => toast.error('Failed to delete category.'),
            onFinish: () => setDeleting(false),
        });
    };

    const columns = createColumns({
        locked,
        onEdit: (category) => {
            setCategoryToEdit(category);
            setSheetOpen(true);
        },
        onDelete: setCategoryToDelete,
    });

    const table = useAppTable({
        columns,
        data: categories,
        initialState: {
            columnPinning: { start: [], end: ['actions'] },
            columnOrder: columns.map((column) => column.id ?? ''),
        },
    });

    return (
        <>
            <Head title="Categories" />

            <table.AppTable>
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <Heading
                            title="Categories"
                            description="Award categories participants choose from when signing up."
                        />
                        <DisabledTooltip
                            reason={locked ? JUDGING_LOCKED_REASON : null}
                        >
                            <Button
                                disabled={locked}
                                onClick={() => {
                                    setCategoryToEdit(null);
                                    setSheetOpen(true);
                                }}
                            >
                                New category
                            </Button>
                        </DisabledTooltip>
                    </div>

                    <JudgingLockAlert locked={locked} />

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 items-center gap-2">
                            <table.Search placeholder="Search categories..." />
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
                                            No categories yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <table.Pagination />
                </div>
            </table.AppTable>

            <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {categoryToEdit ? 'Edit category' : 'New category'}
                        </SheetTitle>
                        <SheetDescription>
                            {categoryToEdit
                                ? 'Changes apply to existing registrations in this category.'
                                : 'Add a category participants can register for.'}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="px-4 pb-4">
                        <CategoryForm
                            key={categoryToEdit?.id ?? 'new'}
                            category={categoryToEdit ?? undefined}
                            templates={templates}
                            nextSortOrder={nextSortOrder}
                            onSaved={() => setSheetOpen(false)}
                        />
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog
                open={categoryToDelete !== null}
                onOpenChange={(open) => !open && setCategoryToDelete(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete category?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete{' '}
                        <strong>{categoryToDelete?.name}</strong>. This action
                        cannot be undone.
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

CategoriesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Categories', href: index() },
    ],
};
