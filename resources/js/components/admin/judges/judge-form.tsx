import { useForm, usePage } from '@inertiajs/react';
import { KeyRound, LockIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { CopyPasswordButton } from '@/components/admin/copy-password-button';
import { JUDGING_LOCKED_REASON } from '@/components/admin/judging-lock-alert';
import { LogoUploadField } from '@/components/admin/logo-upload-field';
import { SaveButton } from '@/components/admin/save-button';
import { SettingsSection } from '@/components/admin/settings-section';
import { StoredPassword } from '@/components/admin/stored-password';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { Textarea } from '@/components/ui/textarea';
import { generatePassword } from '@/lib/password';
import { store, update } from '@/routes/admin/judges';
import type { Auth } from '@/types';

const NO_CATEGORY = 'none';

export type CategoryOption = {
    id: number;
    name: string;
};

export type Assignment = {
    award_category_id: number;
    is_recused: boolean;
};

export type JudgeDetails = {
    id: number;
    name: string;
    position: string;
    institution: string | null;
    bio: string | null;
    show_on_landing: boolean;
    landing_category_id: number | null;
    sort_order: number;
    photo_url: string | null;
    account_email: string | null;
    has_account_password: boolean;
};

type JudgeFormData = {
    photo: File | null;
    remove_photo: boolean;
    name: string;
    position: string;
    institution: string;
    bio: string;
    show_on_landing: boolean;
    landing_category_id: string;
    sort_order: number | '';
    has_account: boolean;
    email: string;
    password: string;
    password_confirmation: string;
    assignments: Assignment[];
};

type JudgeFormProps = {
    judge?: JudgeDetails;
    assignments?: Assignment[];
    categories: CategoryOption[];
    scoredCategoryIds?: number[];
    assignmentsLocked?: boolean;
    accountPassword?: string | null;
};

export function JudgeForm({
    judge,
    assignments = [],
    categories,
    scoredCategoryIds = [],
    assignmentsLocked = false,
    accountPassword,
}: JudgeFormProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const isSuperadmin = auth.user.role === 'superadmin';
    const hasExistingAccount = Boolean(judge?.account_email);
    const [isPasswordVisible, setPasswordVisible] = useState(false);

    const { data, setData, post, processing, errors, transform } =
        useForm<JudgeFormData>({
            photo: null,
            remove_photo: false,
            name: judge?.name ?? '',
            position: judge?.position ?? '',
            institution: judge?.institution ?? '',
            bio: judge?.bio ?? '',
            show_on_landing: judge?.show_on_landing ?? false,
            landing_category_id: judge?.landing_category_id
                ? String(judge.landing_category_id)
                : NO_CATEGORY,
            sort_order: judge?.sort_order ?? 0,
            has_account: hasExistingAccount,
            email: judge?.account_email ?? '',
            password: '',
            password_confirmation: '',
            assignments,
        });

    const fieldErrors = errors as Record<string, string | undefined>;

    const assignmentFor = (categoryId: number) =>
        data.assignments.find(
            (assignment) => assignment.award_category_id === categoryId,
        );

    const toggleAssignment = (categoryId: number, isAssigned: boolean) =>
        setData(
            'assignments',
            isAssigned
                ? [
                      ...data.assignments,
                      { award_category_id: categoryId, is_recused: false },
                  ]
                : data.assignments.filter(
                      (assignment) =>
                          assignment.award_category_id !== categoryId,
                  ),
        );

    const toggleRecused = (categoryId: number, isRecused: boolean) =>
        setData(
            'assignments',
            data.assignments.map((assignment) =>
                assignment.award_category_id === categoryId
                    ? { ...assignment, is_recused: isRecused }
                    : assignment,
            ),
        );

    const fillGeneratedPassword = () => {
        const password = generatePassword();

        setData((current) => ({
            ...current,
            password,
            password_confirmation: password,
        }));
        setPasswordVisible(true);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        transform((form) => ({
            ...form,
            landing_category_id:
                form.landing_category_id === NO_CATEGORY
                    ? ''
                    : form.landing_category_id,
        }));

        post(judge ? update(judge.id).url : store().url, {
            forceFormData: true,
            preserveScroll: true,
            onError: () =>
                toast.error('Failed to save judge. Check the fields below.'),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <SettingsSection
                title="Profile"
                description="Shown to admins and, when enabled, on the landing page."
                defaultOpen
            >
                <div className="grid max-w-2xl gap-4">
                    <LogoUploadField
                        label="Photo"
                        currentUrl={
                            data.remove_photo
                                ? null
                                : (judge?.photo_url ?? null)
                        }
                        file={data.photo}
                        onChange={(file) => setData('photo', file)}
                        onRemoveCurrent={() => setData('remove_photo', true)}
                        error={errors.photo}
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            maxLength={255}
                            required
                            onChange={(e) => setData('name', e.target.value)}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="position">Position</Label>
                            <Input
                                id="position"
                                value={data.position}
                                maxLength={255}
                                required
                                onChange={(e) =>
                                    setData('position', e.target.value)
                                }
                            />
                            <InputError message={errors.position} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="institution">Institution</Label>
                            <Input
                                id="institution"
                                value={data.institution}
                                maxLength={255}
                                onChange={(e) =>
                                    setData('institution', e.target.value)
                                }
                            />
                            <InputError message={errors.institution} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="bio">Bio</Label>
                        <Textarea
                            id="bio"
                            value={data.bio}
                            maxLength={5000}
                            rows={4}
                            onChange={(e) => setData('bio', e.target.value)}
                        />
                        <InputError message={errors.bio} />
                    </div>
                </div>
            </SettingsSection>

            <SettingsSection
                title="Landing page"
                description="How the judge appears on the public landing page."
                defaultOpen
            >
                <div className="grid max-w-2xl gap-4">
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={data.show_on_landing}
                            onCheckedChange={(checked) =>
                                setData('show_on_landing', checked === true)
                            }
                        />
                        Show on landing page
                    </Label>

                    <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_8rem]">
                        <div className="grid gap-2">
                            <Label htmlFor="landing_category_id">
                                Landing category label
                            </Label>
                            <Select
                                value={data.landing_category_id}
                                onValueChange={(value) =>
                                    setData('landing_category_id', value)
                                }
                            >
                                <SelectTrigger id="landing_category_id">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_CATEGORY}>
                                        None
                                    </SelectItem>
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
                            <p className="text-xs text-muted-foreground">
                                Display label only; it does not assign the
                                judge.
                            </p>
                            <InputError message={errors.landing_category_id} />
                        </div>

                        <div className="grid content-start gap-2">
                            <Label htmlFor="sort_order">Sort order</Label>
                            <Input
                                id="sort_order"
                                type="number"
                                min={0}
                                max={9999}
                                required
                                value={data.sort_order}
                                onChange={(e) =>
                                    setData(
                                        'sort_order',
                                        e.target.value === ''
                                            ? ''
                                            : Number(e.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.sort_order} />
                        </div>
                    </div>
                </div>
            </SettingsSection>

            <SettingsSection
                title="Login account"
                description="Lets the judge sign in to score submissions."
                defaultOpen
            >
                <div className="grid max-w-2xl gap-4">
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={data.has_account}
                            disabled={hasExistingAccount}
                            onCheckedChange={(checked) =>
                                setData('has_account', checked === true)
                            }
                        />
                        {hasExistingAccount
                            ? 'This judge has a login account'
                            : 'Create login account'}
                    </Label>
                    <InputError message={errors.has_account} />

                    {data.has_account && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    maxLength={255}
                                    required
                                    autoComplete="off"
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {hasExistingAccount
                                        ? 'New password'
                                        : 'Password'}
                                </Label>
                                <div className="flex gap-2">
                                    <div className="flex-1">
                                        <PasswordInput
                                            id="password"
                                            value={data.password}
                                            required={!hasExistingAccount}
                                            autoComplete="new-password"
                                            className="font-mono"
                                            visible={isPasswordVisible}
                                            onVisibleChange={setPasswordVisible}
                                            onChange={(e) =>
                                                setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <CopyPasswordButton value={data.password} />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={fillGeneratedPassword}
                                    >
                                        <KeyRound />
                                        Generate
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {hasExistingAccount
                                        ? 'Leave blank to keep the current password. '
                                        : ''}
                                    Copy the password before saving
                                    {isSuperadmin
                                        ? '.'
                                        : ' — only a superadmin can view it later.'}
                                </p>
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    value={data.password_confirmation}
                                    required={
                                        !hasExistingAccount ||
                                        data.password !== ''
                                    }
                                    autoComplete="new-password"
                                    className="font-mono"
                                    visible={isPasswordVisible}
                                    onVisibleChange={setPasswordVisible}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>

                            {judge &&
                                isSuperadmin &&
                                judge.has_account_password && (
                                    <div className="grid gap-2 rounded-md border border-dashed p-3">
                                        <Label>Current password</Label>
                                        <StoredPassword
                                            value={accountPassword}
                                            prop="accountPassword"
                                            changedMessage="The judge has changed their password since it was set."
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Visible to superadmins only.
                                        </p>
                                    </div>
                                )}
                        </>
                    )}
                </div>
            </SettingsSection>

            <SettingsSection
                title="Category assignments"
                description="Judges score qualified submissions in their assigned categories. Recused judges do not see that category."
                defaultOpen
            >
                {assignmentsLocked && (
                    <p className="flex items-center gap-2 text-sm text-muted-foreground">
                        <LockIcon className="size-4" />
                        {JUDGING_LOCKED_REASON}
                    </p>
                )}
                <ul className="divide-y rounded-md border">
                    {categories.map((category) => {
                        const assignment = assignmentFor(category.id);
                        const isScored = scoredCategoryIds.includes(
                            category.id,
                        );

                        return (
                            <li
                                key={category.id}
                                className="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Label className="flex items-center gap-2 font-normal">
                                    <Checkbox
                                        checked={assignment !== undefined}
                                        disabled={
                                            assignmentsLocked ||
                                            (isScored &&
                                                assignment !== undefined)
                                        }
                                        onCheckedChange={(checked) =>
                                            toggleAssignment(
                                                category.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    {category.name}
                                    {isScored && (
                                        <span className="text-xs text-muted-foreground">
                                            (scored — can only be recused)
                                        </span>
                                    )}
                                </Label>
                                <Label className="flex items-center gap-2 pl-6 text-sm font-normal text-muted-foreground sm:pl-0">
                                    <Checkbox
                                        checked={
                                            assignment?.is_recused ?? false
                                        }
                                        disabled={
                                            assignmentsLocked ||
                                            assignment === undefined
                                        }
                                        onCheckedChange={(checked) =>
                                            toggleRecused(
                                                category.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    Recused (conflict of interest)
                                </Label>
                            </li>
                        );
                    })}
                </ul>
                <InputError
                    message={
                        errors.assignments ??
                        Object.entries(fieldErrors).find(([key]) =>
                            key.startsWith('assignments.'),
                        )?.[1]
                    }
                />
            </SettingsSection>

            <SaveButton processing={processing} />
        </form>
    );
}
