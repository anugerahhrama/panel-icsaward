import { Head } from '@inertiajs/react';
import {
    JudgeForm,
    type CategoryOption,
} from '@/components/admin/judges/judge-form';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/admin';
import { create, index } from '@/routes/admin/judges';

type Props = {
    categories: CategoryOption[];
    canManageJudgingSetup: boolean;
};

export default function CreateJudge({
    categories,
    canManageJudgingSetup,
}: Props) {
    return (
        <>
            <Head title="New judge" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="New judge"
                    description="Add a judge profile, an optional login account and the categories they score."
                />
                <JudgeForm
                    categories={categories}
                    assignmentsLocked={!canManageJudgingSetup}
                />
            </div>
        </>
    );
}

CreateJudge.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Judges', href: index() },
        { title: 'New judge', href: create() },
    ],
};
