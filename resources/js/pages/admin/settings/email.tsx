import { Form, Head } from '@inertiajs/react';
import EmailSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/EmailSettingsController';
import { SaveButton } from '@/components/admin/save-button';
import { SettingsSection } from '@/components/admin/settings-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import { edit } from '@/routes/admin/settings/email';

type TemplateKey =
    | 'confirmation'
    | 'qualified'
    | 'needs_revision'
    | 'disqualified';

type Props = {
    settings: Record<
        | `${TemplateKey}_email_subject`
        | `${TemplateKey}_email_body`
        | 'contact_email',
        string | null
    >;
};

type Placeholder = { token: string; description: string };

const commonPlaceholders: Placeholder[] = [
    { token: '{{name}}', description: "Participant's name" },
    { token: '{{category}}', description: 'Award category' },
    { token: '{{initiative_title}}', description: 'Initiative title' },
];

const contactPlaceholder: Placeholder = {
    token: '{{contact_email}}',
    description: 'Committee contact email',
};

const dashboardPlaceholder: Placeholder = {
    token: '{{dashboard_link}}',
    description: "Link to the participant's dashboard",
};

const templates: {
    key: TemplateKey;
    title: string;
    description: string;
    placeholders: Placeholder[];
}[] = [
    {
        key: 'confirmation',
        title: 'Registration confirmation',
        description:
            'Sent after sign-up. The body is Markdown; placeholders are replaced per participant.',
        placeholders: [
            ...commonPlaceholders,
            {
                token: '{{submission_link}}',
                description: 'Personal paper upload link',
            },
            { token: '{{deadline}}', description: 'Paper deadline (WIB)' },
            contactPlaceholder,
        ],
    },
    {
        key: 'qualified',
        title: 'Verification: qualified',
        description:
            'Sent when the committee marks a submission as Qualified. The body is Markdown.',
        placeholders: [
            ...commonPlaceholders,
            dashboardPlaceholder,
            contactPlaceholder,
        ],
    },
    {
        key: 'needs_revision',
        title: 'Verification: needs revision',
        description:
            'Sent when the committee asks for a revision. The body is Markdown.',
        placeholders: [
            ...commonPlaceholders,
            {
                token: '{{revision_note}}',
                description: 'Revision note from the committee',
            },
            {
                token: '{{revision_deadline}}',
                description: 'Revision deadline (WIB)',
            },
            dashboardPlaceholder,
            contactPlaceholder,
        ],
    },
    {
        key: 'disqualified',
        title: 'Verification: disqualified',
        description:
            'Sent when the committee disqualifies a submission. The body is Markdown.',
        placeholders: [
            ...commonPlaceholders,
            {
                token: '{{disqualified_reason}}',
                description: 'Reason given by the committee',
            },
            dashboardPlaceholder,
            contactPlaceholder,
        ],
    },
];

export default function EmailSettings({ settings }: Props) {
    return (
        <>
            <Head title="Email Templates" />
            <Form
                {...EmailSettingsController.update.form()}
                options={{ preserveScroll: true }}
                className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
            >
                {({ processing, errors }) => (
                    <>
                        <Heading
                            title="Email Templates"
                            description="Emails sent to participants and the committee contact shown across the app."
                        />

                        {templates.map((template) => {
                            const subject =
                                `${template.key}_email_subject` as const;
                            const body = `${template.key}_email_body` as const;

                            return (
                                <SettingsSection
                                    key={template.key}
                                    title={template.title}
                                    description={template.description}
                                    defaultOpen={
                                        template.key === 'confirmation'
                                    }
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor={subject}>Subject</Label>
                                        <Input
                                            id={subject}
                                            name={subject}
                                            maxLength={255}
                                            required
                                            defaultValue={
                                                settings[subject] ?? ''
                                            }
                                        />
                                        <InputError message={errors[subject]} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor={body}>Body</Label>
                                        <Textarea
                                            id={body}
                                            name={body}
                                            rows={20}
                                            required
                                            className="font-mono text-sm"
                                            defaultValue={settings[body] ?? ''}
                                        />
                                        <InputError message={errors[body]} />
                                    </div>

                                    <div className="grid gap-2">
                                        <p className="text-sm font-medium">
                                            Available placeholders
                                        </p>
                                        <dl className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                                            {template.placeholders.map(
                                                (placeholder) => (
                                                    <div
                                                        key={placeholder.token}
                                                        className="contents"
                                                    >
                                                        <dt>
                                                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                                                {
                                                                    placeholder.token
                                                                }
                                                            </code>
                                                        </dt>
                                                        <dd className="text-muted-foreground">
                                                            {
                                                                placeholder.description
                                                            }
                                                        </dd>
                                                    </div>
                                                ),
                                            )}
                                        </dl>
                                    </div>
                                </SettingsSection>
                            );
                        })}

                        <SettingsSection
                            title="Contact"
                            description="Shown to participants on their dashboard and in emails."
                            defaultOpen
                        >
                            <div className="grid max-w-md gap-2">
                                <Label htmlFor="contact_email">
                                    Contact email
                                </Label>
                                <Input
                                    id="contact_email"
                                    name="contact_email"
                                    type="email"
                                    required
                                    defaultValue={settings.contact_email ?? ''}
                                />
                                <InputError message={errors.contact_email} />
                            </div>
                        </SettingsSection>

                        <SaveButton processing={processing} />
                    </>
                )}
            </Form>
        </>
    );
}

EmailSettings.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Email Templates', href: edit() },
    ],
};
