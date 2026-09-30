import { Head } from '@inertiajs/react';
import { TemplateForm } from '@/components/admin/assessment-templates/template-form';
import Heading from '@/components/heading';
import { dashboard } from '@/routes/admin';
import { create, index } from '@/routes/admin/assessment-templates';

export default function CreateAssessmentTemplate() {
    return (
        <>
            <Head title="New assessment template" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="New assessment template"
                    description="Define the aspects, criteria and weights judges score against."
                />
                <TemplateForm />
            </div>
        </>
    );
}

CreateAssessmentTemplate.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Assessment Templates', href: index() },
        { title: 'New template', href: create() },
    ],
};
