import { Head, router, useForm, useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import GoogleButton from '@/components/auth/google-button';
import { RegisterStepper } from '@/components/auth/register-stepper';
import PasswordInput from '@/components/password-input';
import type {
    ApplicantType,
    AwardCategory,
} from '@/components/submissions/initiative-fields';
import {
    findCategory,
    INITIATIVE_FIELDS,
    InitiativeFields,
} from '@/components/submissions/initiative-fields';
import { TermsFields } from '@/components/submissions/terms-fields';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { toastFormErrors } from '@/lib/form-errors';
import { login } from '@/routes';
import { cancel as cancelGoogleSignup } from '@/routes/auth/google/signup';
import { store, validate } from '@/routes/register';

type GoogleSignup = {
    name: string;
    email: string;
};

type Props = {
    passwordRules: string;
    googleSignup: GoogleSignup | null;
    categories: AwardCategory[];
    terms: Record<ApplicantType, string>;
};

type RegisterForm = {
    name: string;
    phone: string;
    email: string;
    position: string;
    company_name: string;
    password: string;
    password_confirmation: string;
    award_category_id: string;
    initiative_title: string;
    initiative_description: string;
    terms_accepted: boolean;
};

const STEPS = [
    {
        key: 'account',
        label: 'Account',
        fields: [
            'name',
            'phone',
            'email',
            'position',
            'company_name',
            'password',
            'password_confirmation',
        ],
    },
    {
        key: 'initiative',
        label: 'Initiative',
        fields: INITIATIVE_FIELDS,
    },
    {
        key: 'terms',
        label: 'Terms',
        fields: ['terms_accepted'],
    },
] as const satisfies readonly {
    key: string;
    label: string;
    fields: readonly (keyof RegisterForm)[];
}[];

export default function Register({
    passwordRules,
    googleSignup,
    categories,
    terms,
}: Props) {
    const [step, setStep] = useState(0);

    const form = useForm<RegisterForm>({
        name: googleSignup?.name ?? '',
        phone: '',
        email: googleSignup?.email ?? '',
        position: '',
        company_name: '',
        password: '',
        password_confirmation: '',
        award_category_id: '',
        initiative_title: '',
        initiative_description: '',
        terms_accepted: false,
    });
    const stepValidation = useHttp<RegisterForm>();
    const { data, setData, errors } = form;

    const currentStep = STEPS[step];
    const isLastStep = step === STEPS.length - 1;
    const isProcessing = form.processing || stepValidation.processing;

    const selectedCategory = findCategory(categories, data.award_category_id);

    function goToStep(index: number) {
        setStep(index);
        window.scrollTo({ top: 0 });
    }

    function goToNextStep() {
        const fields = currentStep.fields;

        stepValidation.transform(() => form.data);
        void stepValidation.post(validate.url(currentStep.key), {
            onSuccess: () => {
                form.clearErrors(...fields);
                goToStep(step + 1);
            },
            onError: (stepErrors) => {
                if ('registration' in stepErrors) {
                    router.reload();

                    return;
                }

                form.clearErrors(...fields);
                form.setError(
                    stepErrors as Partial<Record<keyof RegisterForm, string>>,
                );
                toastFormErrors(stepErrors);
            },
        });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!isLastStep) {
            goToNextStep();

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
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Register" />
            <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
                <RegisterStepper
                    steps={STEPS.map((candidate) => candidate.label)}
                    currentStep={step}
                />

                {currentStep.key === 'account' && (
                    <div className="grid gap-5">
                        {googleSignup ? (
                            <p className="rounded-lg border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
                                Signing up with Google as{' '}
                                <span className="font-medium text-foreground">
                                    {googleSignup.email}
                                </span>
                                . Not you?{' '}
                                <TextLink
                                    href={cancelGoogleSignup()}
                                    as="button"
                                >
                                    Use email instead
                                </TextLink>
                            </p>
                        ) : (
                            <>
                                <GoogleButton>Sign up with Google</GoogleButton>

                                <div className="flex items-center gap-3 text-xs text-muted-foreground uppercase">
                                    <span className="h-px flex-1 bg-border" />
                                    or
                                    <span className="h-px flex-1 bg-border" />
                                </div>
                            </>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="name">Full name</Label>
                            <Input
                                id="name"
                                aria-invalid={Boolean(errors.name)}
                                autoFocus
                                autoComplete="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Your full name"
                            />
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    aria-invalid={Boolean(errors.email)}
                                    type="email"
                                    autoComplete="email"
                                    readOnly={googleSignup !== null}
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="email@company.com"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone number</Label>
                                <Input
                                    id="phone"
                                    aria-invalid={Boolean(errors.phone)}
                                    type="tel"
                                    autoComplete="tel"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                    placeholder="+62 812-3456-7890"
                                />
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="position">Position</Label>
                                <Input
                                    id="position"
                                    aria-invalid={Boolean(errors.position)}
                                    autoComplete="organization-title"
                                    value={data.position}
                                    onChange={(e) =>
                                        setData('position', e.target.value)
                                    }
                                    placeholder="e.g. Sustainability Manager"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_name">
                                    Company name
                                </Label>
                                <Input
                                    id="company_name"
                                    aria-invalid={Boolean(errors.company_name)}
                                    autoComplete="organization"
                                    value={data.company_name}
                                    onChange={(e) =>
                                        setData('company_name', e.target.value)
                                    }
                                    placeholder="PT Example Indonesia"
                                />
                            </div>
                        </div>

                        {googleSignup === null && (
                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        aria-invalid={Boolean(errors.password)}
                                        autoComplete="new-password"
                                        value={data.password}
                                        onChange={(e) =>
                                            setData('password', e.target.value)
                                        }
                                        placeholder="Password"
                                        passwordrules={passwordRules}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        aria-invalid={Boolean(
                                            errors.password_confirmation,
                                        )}
                                        autoComplete="new-password"
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                'password_confirmation',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Confirm password"
                                        passwordrules={passwordRules}
                                    />
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {currentStep.key === 'initiative' && (
                    <InitiativeFields
                        categories={categories}
                        data={data}
                        errors={errors}
                        setData={setData}
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
                        data-test={
                            isLastStep ? 'register-user-button' : undefined
                        }
                    >
                        {isProcessing && <Spinner />}
                        {isLastStep ? 'Submit registration' : 'Continue'}
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <TextLink href={login()}>Log in</TextLink>
                </div>
            </form>
        </>
    );
}

Register.layout = {
    wide: true,
    title: 'Register your initiative',
    description:
        'Create your account and register an initiative for ICS Award 2026.',
};
