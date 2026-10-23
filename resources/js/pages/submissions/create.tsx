import { Head, router, useForm, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { RegisterStepper } from '@/components/auth/register-stepper';
import type {
    ApplicantType,
    AwardCategory,
    InitiativeData,
} from '@/components/submissions/initiative-fields';
import {
    findCategory,
    INITIATIVE_FIELDS,
    InitiativeFields,
} from '@/components/submissions/initiative-fields';
import { TermsFields } from '@/components/submissions/terms-fields';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { toastFormErrors } from '@/lib/form-errors';
import { dashboard } from '@/routes';
import { store, validate } from '@/routes/submissions';

type Props = {
    categories: AwardCategory[];
    terms: Record<ApplicantType, string>;
    registeredCategoryIds: number[];
};

type InitiativeForm = InitiativeData & {
    terms_accepted: boolean;
};

const STEPS = [
    { key: 'initiative', label: 'Initiative', fields: INITIATIVE_FIELDS },
    { key: 'terms', label: 'Terms', fields: ['terms_accepted'] },
] as const satisfies readonly {
    key: string;
    label: string;
    fields: readonly (keyof InitiativeForm)[];
}[];

export default function CreateSubmission({
    categories,
    terms,
    registeredCategoryIds,
}: Props) {
    const [step, setStep] = useState(0);

    const form = useForm<InitiativeForm>({
        award_category_id: '',
        initiative_title: '',
        initiative_description: '',
        terms_accepted: false,
    });
    const stepValidation = useHttp<InitiativeForm>();
    const { data, setData, errors } = form;

    const currentStep = STEPS[step];
    const isLastStep = step === STEPS.length - 1;
    const isProcessing = form.processing || stepValidation.processing;
    const selectedCategory = findCategory(categories, data.award_category_id);

    function goToStep(index: number) {
        setStep(index);
        window.scrollTo({ top: 0 });
    }

    function goToTerms() {
        stepValidation.transform(() => form.data);
        void stepValidation.post(validate.url(), {
            onSuccess: () => {
                form.clearErrors(...INITIATIVE_FIELDS);
                goToStep(1);
            },
            onError: (stepErrors) => {
                if ('registration' in stepErrors) {
                    router.reload();

                    return;
                }

                form.clearErrors(...INITIATIVE_FIELDS);
                form.setError(
                    stepErrors as Partial<Record<keyof InitiativeForm, string>>,
                );
                toastFormErrors(stepErrors);
            },
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!isLastStep) {
            goToTerms();

            return;
        }

        form.post(store.url(), {
            onError: (submitErrors) => {
                toastFormErrors(submitErrors);

                const firstStepWithErrors = STEPS.findIndex((candidate) =>
                    candidate.fields.some((field) => field in submitErrors),
                );

                if (firstStepWithErrors !== -1) {
                    goToStep(firstStepWithErrors);
                }
            },
        });
    }

    return (
        <>
            <Head title="Register another initiative" />
            <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
                <RegisterStepper
                    steps={STEPS.map((candidate) => candidate.label)}
                    currentStep={step}
                />

                {currentStep.key === 'initiative' && (
                    <InitiativeFields
                        categories={categories}
                        data={data}
                        errors={errors}
                        setData={setData}
                        registeredCategoryIds={registeredCategoryIds}
                    />
                )}

                {currentStep.key === 'terms' && (
                    <TermsFields
                        category={selectedCategory}
                        terms={terms}
                        accepted={data.terms_accepted}
                        invalid={Boolean(errors.terms_accepted)}
                        onAcceptedChange={(accepted) =>
                            setData('terms_accepted', accepted)
                        }
                    />
                )}

                <div className="flex items-center gap-3">
                    {step > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToStep(step - 1)}
                            disabled={isProcessing}
                        >
                            Back
                        </Button>
                    )}
                    <Button
                        type="submit"
                        className="flex-1"
                        disabled={isProcessing}
                    >
                        {isProcessing && <Spinner />}
                        {isLastStep ? 'Submit registration' : 'Continue'}
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    <TextLink href={dashboard()}>Back to dashboard</TextLink>
                </div>
            </form>
        </>
    );
}

CreateSubmission.layout = {
    wide: true,
    title: 'Register another initiative',
    description:
        'Register one more initiative in a different award category for ICS Award 2026.',
};
