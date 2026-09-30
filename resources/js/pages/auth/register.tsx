import { Head, router, useForm, useHttp } from '@inertiajs/react';
import { Download } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { RegisterStepper } from '@/components/auth/register-stepper';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { login } from '@/routes';
import { store, validate } from '@/routes/register';

type ApplicantType = 'organization' | 'individual';

type AwardCategory = {
    id: number;
    name: string;
    description: string | null;
    applicant_type: ApplicantType;
    paper_template_url: string | null;
};

type Props = {
    passwordRules: string;
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
        fields: [
            'award_category_id',
            'initiative_title',
            'initiative_description',
        ],
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

const DESCRIPTION_MAX_LENGTH = 1000;

const APPLICANT_TYPE_LABELS: Record<ApplicantType, string> = {
    organization: 'Organization',
    individual: 'Individual',
};

export default function Register({ passwordRules, categories, terms }: Props) {
    const [step, setStep] = useState(0);

    const form = useForm<RegisterForm>({
        name: '',
        phone: '',
        email: '',
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

    const selectedCategory = categories.find(
        (category) => String(category.id) === data.award_category_id,
    );
    const applicantType = selectedCategory?.applicant_type ?? 'organization';
    const termPoints = terms[applicantType]
        .split('\n')
        .map((point) => point.trim())
        .filter((point) => point !== '');

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
                        <div className="grid gap-2">
                            <Label htmlFor="name">Full name</Label>
                            <Input
                                id="name"
                                autoFocus
                                autoComplete="name"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Your full name"
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="email@company.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">Phone number</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    autoComplete="tel"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                    placeholder="+62 812-3456-7890"
                                />
                                <InputError message={errors.phone} />
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="position">Position</Label>
                                <Input
                                    id="position"
                                    autoComplete="organization-title"
                                    value={data.position}
                                    onChange={(e) =>
                                        setData('position', e.target.value)
                                    }
                                    placeholder="e.g. Sustainability Manager"
                                />
                                <InputError message={errors.position} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="company_name">
                                    Company name
                                </Label>
                                <Input
                                    id="company_name"
                                    autoComplete="organization"
                                    value={data.company_name}
                                    onChange={(e) =>
                                        setData('company_name', e.target.value)
                                    }
                                    placeholder="PT Example Indonesia"
                                />
                                <InputError message={errors.company_name} />
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
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
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                        </div>
                    </div>
                )}

                {currentStep.key === 'initiative' && (
                    <div className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="award_category_id">
                                Award category
                            </Label>
                            <Select
                                value={data.award_category_id}
                                onValueChange={(value) =>
                                    setData('award_category_id', value)
                                }
                            >
                                <SelectTrigger
                                    id="award_category_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Choose a category" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {selectedCategory && (
                                <p className="text-xs text-muted-foreground">
                                    {APPLICANT_TYPE_LABELS[applicantType]}{' '}
                                    category
                                    {selectedCategory.description &&
                                        ` · ${selectedCategory.description}`}
                                </p>
                            )}
                            <InputError message={errors.award_category_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="initiative_title">
                                Initiative title
                            </Label>
                            <Input
                                id="initiative_title"
                                value={data.initiative_title}
                                onChange={(e) =>
                                    setData('initiative_title', e.target.value)
                                }
                                placeholder="The name of your initiative"
                            />
                            <InputError message={errors.initiative_title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="initiative_description">
                                Short description
                            </Label>
                            <Textarea
                                id="initiative_description"
                                rows={5}
                                maxLength={DESCRIPTION_MAX_LENGTH}
                                value={data.initiative_description}
                                onChange={(e) =>
                                    setData(
                                        'initiative_description',
                                        e.target.value,
                                    )
                                }
                                placeholder="Briefly describe what the initiative does and its impact"
                            />
                            <p className="text-right text-xs text-muted-foreground">
                                {data.initiative_description.length}/
                                {DESCRIPTION_MAX_LENGTH}
                            </p>
                            <InputError
                                message={errors.initiative_description}
                            />
                        </div>

                        {selectedCategory?.paper_template_url && (
                            <Button variant="outline" asChild>
                                <a
                                    href={selectedCategory.paper_template_url}
                                    download
                                >
                                    <Download />
                                    Download submission paper template
                                </a>
                            </Button>
                        )}
                    </div>
                )}

                {currentStep.key === 'terms' && (
                    <div className="grid gap-4">
                        <div className="grid gap-1">
                            <h2 className="font-display text-base font-semibold">
                                Terms &amp; Conditions —{' '}
                                {APPLICANT_TYPE_LABELS[applicantType]}
                            </h2>
                            {selectedCategory && (
                                <p className="text-sm text-muted-foreground">
                                    {selectedCategory.name}
                                </p>
                            )}
                        </div>

                        <ol className="max-h-72 list-decimal space-y-2 overflow-y-auto rounded-lg border bg-surface p-4 pl-9 text-sm text-ink dark:bg-muted dark:text-foreground">
                            {termPoints.map((point, index) => (
                                <li key={index}>{point}</li>
                            ))}
                        </ol>

                        <div className="grid gap-2">
                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="terms_accepted"
                                    checked={data.terms_accepted}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'terms_accepted',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="terms_accepted"
                                    className="leading-snug font-normal"
                                >
                                    I have read and agree to the terms and
                                    conditions of ICS Award 2026.
                                </Label>
                            </div>
                            <InputError message={errors.terms_accepted} />
                        </div>
                    </div>
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
