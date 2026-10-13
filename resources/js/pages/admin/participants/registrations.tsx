import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ParticipantDetailsSheet } from '@/components/admin/participants/participant-details-sheet';
import {
    ParticipantsTable,
    type ParticipantTableProps,
} from '@/components/admin/participants/participants-table';
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
import { dashboard } from '@/routes/admin';
import { destroy, index } from '@/routes/admin/participants/registrations';
import type { Auth } from '@/types';
import {
    createRegistrationColumns,
    type RegistrationRow,
} from './registrations-columns';

export default function Registrations(
    props: ParticipantTableProps<RegistrationRow>,
) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [pendingDelete, setPendingDelete] = useState<RegistrationRow | null>(
        null,
    );
    const [deleting, setDeleting] = useState(false);
    const { auth } = usePage<{ auth: Auth }>().props;
    const isSuperadmin = auth.user.role === 'superadmin';

    // Read the row from the latest props so the sheet stays in sync after a reload.
    const selected =
        props.submissions.data.find(
            (registration) => registration.id === selectedId,
        ) ?? null;

    const columns = createRegistrationColumns({
        onView: (registration) => {
            setSelectedId(registration.id);
            setSheetOpen(true);
        },
    });

    const confirmDelete = () => {
        if (!pendingDelete) {
            return;
        }

        setDeleting(true);
        router.delete(destroy(pendingDelete.uuid).url, {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDelete(null);
                setSheetOpen(false);
            },
            onError: () => toast.error('Failed to delete registration.'),
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title="Registrations" />
            <ParticipantsTable
                {...props}
                tab="registrations"
                description="Everyone who signed up, whether or not they have uploaded a paper."
                columns={columns}
                defaultSort="-created_at"
                emptyMessage="No registrations match these filters."
            />
            <ParticipantDetailsSheet
                registration={selected}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                onDelete={isSuperadmin ? setPendingDelete : undefined}
            />
            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
            >
                <DialogContent>
                    <DialogTitle>Delete registration?</DialogTitle>
                    <DialogDescription>
                        <strong>{pendingDelete?.initiative_title}</strong> by{' '}
                        {pendingDelete?.name} ({pendingDelete?.email}) and its
                        uploaded files will be permanently deleted. This cannot
                        be undone.
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

Registrations.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Registrations', href: index() },
    ],
};
