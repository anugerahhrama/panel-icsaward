import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ParticipantDetailsSheet } from '@/components/admin/participants/participant-details-sheet';
import {
    ParticipantsTable,
    type ParticipantTableProps,
} from '@/components/admin/participants/participants-table';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/participants/registrations';
import {
    createRegistrationColumns,
    type RegistrationRow,
} from './registrations-columns';

export default function Registrations(
    props: ParticipantTableProps<RegistrationRow>,
) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [selectedId, setSelectedId] = useState<number | null>(null);

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
            />
        </>
    );
}

Registrations.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Registrations', href: index() },
    ],
};
