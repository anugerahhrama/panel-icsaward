import { Head } from '@inertiajs/react';
import { JudgingLockAlert } from '@/components/admin/judging-lock-alert';
import {
    ScheduleForm,
    type PitchingFinalist,
    type PitchingSessionDetails,
} from '@/components/admin/pitching/schedule-form';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/pitching';

type Props = {
    category: { id: number; name: string };
    session: PitchingSessionDetails | null;
    finalists: PitchingFinalist[];
    canManageJudgingSetup: boolean;
};

export default function EditPitchingSchedule({
    category,
    session,
    finalists,
    canManageJudgingSetup,
}: Props) {
    return (
        <>
            <Head title={`Pitching · ${category.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={category.name}
                    description="Finalists see their pitching time, location, and meeting link on their dashboard once saved."
                />
                <JudgingLockAlert locked={!canManageJudgingSetup} />
                <ScheduleForm
                    category={category}
                    session={session}
                    finalists={finalists}
                    readOnly={!canManageJudgingSetup}
                />
            </div>
        </>
    );
}

EditPitchingSchedule.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Pitching', href: index() },
    ],
};
