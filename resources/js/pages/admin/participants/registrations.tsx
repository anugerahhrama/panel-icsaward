import { Head } from '@inertiajs/react';
import {
    ParticipantsTable,
    type ParticipantTableProps,
} from '@/components/admin/participants/participants-table';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/participants/registrations';
import {
    registrationColumns,
    type RegistrationRow,
} from './registrations-columns';

export default function Registrations(
    props: ParticipantTableProps<RegistrationRow>,
) {
    return (
        <>
            <Head title="Registrations" />
            <ParticipantsTable
                {...props}
                tab="registrations"
                description="Everyone who signed up, whether or not they have uploaded a paper."
                columns={registrationColumns}
                defaultSort="-created_at"
                emptyMessage="No registrations match these filters."
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
