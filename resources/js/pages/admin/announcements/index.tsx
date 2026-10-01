import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import AnnouncementController from '@/actions/App/Http/Controllers/Admin/AnnouncementController';
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
import { index } from '@/routes/admin/announcements';
import {
    type AnnouncementCategoryRow,
    type AnnouncementType,
    createColumns,
} from './columns';

type Props = {
    categories: AnnouncementCategoryRow[];
};

type Pending = { category: AnnouncementCategoryRow; type: AnnouncementType };

const CONFIRM_TITLES: Record<AnnouncementType, string> = {
    finalists: 'Announce finalists?',
    invitations: 'Send Awarding Night invitations?',
    winners: 'Announce winners?',
};

const CONFIRM_DESCRIPTIONS: Record<AnnouncementType, string> = {
    finalists:
        'Each finalist is emailed with their pitching schedule (if set), and every participant of this category sees the result on their dashboard. The finalists can no longer be reopened.',
    invitations:
        'Each finalist is emailed an invitation to the Awarding Night and sees it on their dashboard. Their award is not revealed.',
    winners:
        'Each award recipient is emailed their award, which also appears on their dashboard. The awards can no longer be reopened.',
};

export default function AnnouncementsIndex({ categories }: Props) {
    const [pending, setPending] = useState<Pending | null>(null);
    const [processing, setProcessing] = useState(false);

    const columns = createColumns({
        onAnnounce: (category, type) => setPending({ category, type }),
    });

    const table = useAppTable({
        columns,
        data: categories,
        initialState: {
            columnOrder: columns.map((column) => column.id ?? ''),
        },
    });

    const announce = () => {
        if (!pending) {
            return;
        }

        setProcessing(true);

        router.post(
            AnnouncementController.store([pending.category.id, pending.type])
                .url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setPending(null),
                onError: () => toast.error('Failed to make the announcement.'),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const recipients = pending
        ? pending.category.announcements[pending.type].recipients_count
        : 0;

    return (
        <>
            <Head title="Announcements" />

            <table.AppTable>
                <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <Heading
                        title="Announcements"
                        description="Tell participants the results of each category, in order: finalists, Awarding Night invitations, then winners. Each step emails the recipients and cannot be undone."
                    />

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-1 items-center gap-2">
                            <table.Search placeholder="Search categories..." />
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
                                                    <TableCell
                                                        key={cell.id}
                                                        className="align-top"
                                                    >
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

            <Dialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
            >
                <DialogContent>
                    <DialogTitle>
                        {pending && CONFIRM_TITLES[pending.type]}
                    </DialogTitle>
                    <DialogDescription>
                        <strong>{pending?.category.name}</strong>: {recipients}{' '}
                        {recipients === 1 ? 'recipient' : 'recipients'}.{' '}
                        {pending && CONFIRM_DESCRIPTIONS[pending.type]} This
                        cannot be undone.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button disabled={processing} onClick={announce}>
                            {processing && <Spinner />}
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AnnouncementsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Announcements', href: index() },
    ],
};
