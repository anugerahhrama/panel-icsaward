import { Form, Head } from '@inertiajs/react';
import RegistrationSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/RegistrationSettingsController';
import { SaveButton } from '@/components/admin/save-button';
import { SettingsSection } from '@/components/admin/settings-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/admin';
import { edit } from '@/routes/admin/settings/registration';

type TimelineKey =
    | 'timeline_administrative_selection'
    | 'timeline_desk_evaluation'
    | 'timeline_finalists_announcement'
    | 'timeline_pitching'
    | 'timeline_awarding_night';

type Props = {
    settings: Record<
        | 'registration_opens_at'
        | 'registration_deadline'
        | 'paper_deadline'
        | 'max_registrations_per_user'
        | TimelineKey,
        string | null
    > & { is_registration_open: boolean };
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

const timelineFields: { name: TimelineKey; label: string }[] = [
    {
        name: 'timeline_administrative_selection',
        label: 'Administrative Selection',
    },
    { name: 'timeline_desk_evaluation', label: 'Desk Evaluation' },
    {
        name: 'timeline_finalists_announcement',
        label: 'Top 5 Finalists Announcement',
    },
    { name: 'timeline_pitching', label: 'Pitching' },
    { name: 'timeline_awarding_night', label: 'Awarding Night' },
];

export default function RegistrationSettings({ settings }: Props) {
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
                            description={`Display text for the "What's next" panel, e.g. "12 – 30 October 2026". Not used to open or close stages.`}
                            defaultOpen
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                {timelineFields.map((field) => (
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
                                            maxLength={255}
                                            defaultValue={
                                                settings[field.name] ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors[field.name]}
                                        />
                                    </div>
                                ))}
                            </div>
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
