import { Form, Head } from '@inertiajs/react';
import RegistrationSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/RegistrationSettingsController';
import { SaveButton } from '@/components/admin/save-button';
import { SettingsSection } from '@/components/admin/settings-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import { edit } from '@/routes/admin/settings/registration';

type TimelineStage = {
    dateKey: string;
    titleKey: string;
    descriptionKey: string;
    defaultTitle: string;
    defaultDescription: string;
};

type Props = {
    settings: Record<
        | 'registration_opens_at'
        | 'registration_deadline'
        | 'paper_deadline'
        | 'max_registrations_per_user',
        string | null
    > &
        Record<string, string | null> & { is_registration_open: boolean };
    timelineStages: TimelineStage[];
};

const dateFields = [
    {
        name: 'registration_opens_at',
        label: 'Registration opens',
        hint: 'Sign-up opens at 00:00 WIB.',
    },
    {
        name: 'registration_deadline',
        label: 'Registration deadline',
        hint: 'End of day, WIB.',
    },
    {
        name: 'paper_deadline',
        label: 'Paper deadline',
        hint: 'End of day, WIB. Uploads lock after this moment.',
    },
] as const;

export default function RegistrationSettings({
    settings,
    timelineStages,
}: Props) {
    return (
        <>
            <Head title="Registration & Deadlines" />
            <Form
                {...RegistrationSettingsController.update.form()}
                options={{ preserveScroll: true }}
                className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <Heading
                            title="Registration & Deadlines"
                            description="Registration period, paper deadline and the stage dates participants see on their dashboard."
                        />

                        <SettingsSection
                            title="Registration"
                            description="Dates are entered in Western Indonesian Time (WIB)."
                            defaultOpen
                        >
                            <div className="grid gap-1">
                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <Checkbox
                                        name="is_registration_open"
                                        value="1"
                                        defaultChecked={
                                            settings.is_registration_open
                                        }
                                    />
                                    Registration open
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Turn off to close sign-up immediately,
                                    regardless of the dates below.
                                </p>
                                <InputError
                                    message={errors.is_registration_open}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                {dateFields.map((field) => (
                                    <div
                                        key={field.name}
                                        className="grid gap-2"
                                    >
                                        <Label htmlFor={field.name}>
                                            {field.label}
                                        </Label>
                                        <Input
                                            id={field.name}
                                            name={field.name}
                                            type="date"
                                            required
                                            defaultValue={
                                                settings[field.name] ?? ''
                                            }
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {field.hint}
                                        </p>
                                        <InputError
                                            message={errors[field.name]}
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="grid max-w-xs gap-2">
                                <Label htmlFor="max_registrations_per_user">
                                    Registrations per account
                                </Label>
                                <Input
                                    id="max_registrations_per_user"
                                    name="max_registrations_per_user"
                                    type="number"
                                    min={1}
                                    max={20}
                                    required
                                    defaultValue={
                                        settings.max_registrations_per_user ??
                                        '1'
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    One initiative per category still applies.
                                </p>
                                <InputError
                                    message={errors.max_registrations_per_user}
                                />
                            </div>
                        </SettingsSection>

                        <SettingsSection
                            title="Competition timeline"
                            description={`Text for the "What's next" panel on the participant dashboard. Dates are display text, e.g. "12 – 30 October 2026", and do not open or close stages. Leave a title or description empty to use the default text.`}
                            defaultOpen
                        >
                            {timelineStages.map((stage, index) => (
                                <fieldset
                                    key={stage.dateKey}
                                    className="grid gap-4 rounded-lg border p-4"
                                >
                                    <legend className="px-1 text-sm font-medium">
                                        Stage {index + 1}
                                    </legend>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor={stage.titleKey}>
                                                Title
                                            </Label>
                                            <Input
                                                id={stage.titleKey}
                                                name={stage.titleKey}
                                                maxLength={100}
                                                placeholder={stage.defaultTitle}
                                                defaultValue={
                                                    settings[stage.titleKey] ??
                                                    stage.defaultTitle
                                                }
                                            />
                                            <InputError
                                                message={errors[stage.titleKey]}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={stage.dateKey}>
                                                Date
                                            </Label>
                                            <Input
                                                id={stage.dateKey}
                                                name={stage.dateKey}
                                                maxLength={255}
                                                defaultValue={
                                                    settings[stage.dateKey] ??
                                                    ''
                                                }
                                            />
                                            <InputError
                                                message={errors[stage.dateKey]}
                                            />
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={stage.descriptionKey}>
                                            Description
                                        </Label>
                                        <Textarea
                                            id={stage.descriptionKey}
                                            name={stage.descriptionKey}
                                            rows={2}
                                            maxLength={500}
                                            placeholder={
                                                stage.defaultDescription
                                            }
                                            defaultValue={
                                                settings[
                                                    stage.descriptionKey
                                                ] ?? stage.defaultDescription
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[stage.descriptionKey]
                                            }
                                        />
                                    </div>
                                </fieldset>
                            ))}
                        </SettingsSection>

                        <SaveButton processing={processing} />
                    </>
                )}
            </Form>
        </>
    );
}

RegistrationSettings.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Registration & Deadlines', href: edit() },
    ],
};
