import { Head } from '@inertiajs/react';
import {
    JudgeForm,
    type Assignment,
    type CategoryOption,
    type JudgeDetails,
} from '@/components/admin/judges/judge-form';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/judges';

type Props = {
    judge: JudgeDetails;
    assignments: Assignment[];
    categories: CategoryOption[];
    scoredCategoryIds: number[];
    canManageJudgingSetup: boolean;
    accountPassword?: string | null;
};

export default function EditJudge({
    judge,
    assignments,
    categories,
    scoredCategoryIds,
    canManageJudgingSetup,
    accountPassword,
}: Props) {
    return (
        <>
            <Head title={`Edit ${judge.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={judge.name}
                    description={[judge.position, judge.institution]
                        .filter(Boolean)
                        .join(' · ')}
                />
                <JudgeForm
                    judge={judge}
                    assignments={assignments}
                    categories={categories}
                    scoredCategoryIds={scoredCategoryIds}
                    assignmentsLocked={!canManageJudgingSetup}
                    accountPassword={accountPassword}
                />
            </div>
        </>
    );
}

EditJudge.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Judges', href: index() },
    ],
};
