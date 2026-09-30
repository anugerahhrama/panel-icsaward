import { Head } from '@inertiajs/react';
import {
    TemplateForm,
    type CriterionRow,
    type TemplateDetails,
} from '@/components/admin/assessment-templates/template-form';
import { JudgingLockAlert } from '@/components/admin/judging-lock-alert';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/admin';
import { index } from '@/routes/admin/assessment-templates';

type Props = {
    template: TemplateDetails;
    criteria: CriterionRow[];
    categories: string[];
    canManageJudgingSetup: boolean;
};

export default function EditAssessmentTemplate({
    template,
    criteria,
    categories,
    canManageJudgingSetup,
}: Props) {
    return (
        <>
            <Head title={`Edit ${template.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={template.name}
                    description={
                        categories.length > 0
                            ? `Used by ${categories.join(', ')}.`
                            : 'Not used by any category yet.'
                    }
                />
                <JudgingLockAlert locked={!canManageJudgingSetup} />
                <TemplateForm
                    template={template}
                    criteria={criteria}
                    readOnly={!canManageJudgingSetup}
                />
            </div>
        </>
    );
}

EditAssessmentTemplate.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Assessment Templates', href: index() },
    ],
};
