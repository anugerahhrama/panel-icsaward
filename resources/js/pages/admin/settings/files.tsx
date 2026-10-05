import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FileSettingsController from '@/actions/App/Http/Controllers/Admin/Settings/FileSettingsController';
import { LogoUploadField } from '@/components/admin/logo-upload-field';
import { SaveButton } from '@/components/admin/save-button';
import { SettingsSection } from '@/components/admin/settings-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes/admin';
import { edit } from '@/routes/admin/settings/files';

type TemplateKey = 'submission_template' | 'statement_letter_template';

type Props = {
    settings: {
        paper_allowed_extensions: string[];
        paper_max_size_mb: string | null;
        require_statement_letter: boolean;
        terms_organization: string | null;
        terms_individual: string | null;
    };
    templates: Record<TemplateKey, { url: string | null; name: string | null }>;
    paperExtensionOptions: string[];
};

type FileSettingsForm = {
    submission_template: File | null;
    remove_submission_template: boolean;
    statement_letter_template: File | null;
    remove_statement_letter_template: boolean;
    paper_allowed_extensions: string[];
    paper_max_size_mb: string;
    require_statement_letter: boolean;
    terms_organization: string;
    terms_individual: string;
};

const templateFields: { key: TemplateKey; label: string }[] = [
    {
        key: 'submission_template',
        label: 'Default Submission Paper Template',
    },
    { key: 'statement_letter_template', label: 'Statement Letter Template' },
];

export default function FileSettings({
    settings,
    templates,
    paperExtensionOptions,
}: Props) {
    const form = useForm<FileSettingsForm>({
        submission_template: null,
        remove_submission_template: false,
        statement_letter_template: null,
        remove_statement_letter_template: false,
        paper_allowed_extensions: settings.paper_allowed_extensions,
        paper_max_size_mb: settings.paper_max_size_mb ?? '20',
        require_statement_letter: settings.require_statement_letter,
        terms_organization: settings.terms_organization ?? '',
        terms_individual: settings.terms_individual ?? '',
    });

    function toggleExtension(extension: string, isChecked: boolean): void {
        form.setData(
            'paper_allowed_extensions',
            isChecked
                ? [...form.data.paper_allowed_extensions, extension]
                : form.data.paper_allowed_extensions.filter(
                      (value) => value !== extension,
                  ),
        );
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.submit(FileSettingsController.update(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () =>
                form.reset(
                    'submission_template',
                    'remove_submission_template',
                    'statement_letter_template',
                    'remove_statement_letter_template',
                ),
        });
    }

    return (
        <>
            <Head title="Files & Terms" />
            <form
                onSubmit={submit}
                className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
            >
                <Heading
                    title="Files & Terms"
                    description="Downloadable templates, paper upload rules and the terms participants accept at sign-up."
                />

                <SettingsSection
                    title="Templates"
                    description="Participants download these from the upload page and their dashboard. The default paper template is used for categories without their own paper template. PDF, Word, or PowerPoint, max 10 MB."
                    defaultOpen
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        {templateFields.map(({ key, label }) => (
                            <LogoUploadField
                                key={key}
                                label={label}
                                accept=".pdf,.doc,.docx,.pptx"
                                hint="Click to choose a PDF, Word, or PowerPoint file"
                                currentUrl={
                                    form.data[`remove_${key}`]
                                        ? null
                                        : templates[key].url
                                }
                                currentName={templates[key].name}
                                file={form.data[key]}
                                onChange={(file) => form.setData(key, file)}
                                onRemoveCurrent={() =>
                                    form.setData(`remove_${key}`, true)
                                }
                                error={form.errors[key]}
                            />
                        ))}
                    </div>
                </SettingsSection>

                <SettingsSection
                    title="Paper upload"
                    description="Rules applied when participants upload their Submission Paper."
                    defaultOpen
                >
                    <div className="grid gap-2">
                        <Label>Allowed file types</Label>
                        <div className="flex flex-wrap gap-4">
                            {paperExtensionOptions.map((extension) => (
                                <label
                                    key={extension}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={form.data.paper_allowed_extensions.includes(
                                            extension,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggleExtension(
                                                extension,
                                                checked === true,
                                            )
                                        }
                                    />
                                    .{extension}
                                </label>
                            ))}
                        </div>
                        <InputError
                            message={
                                form.errors.paper_allowed_extensions ??
                                form.errors['paper_allowed_extensions.0']
                            }
                        />
                    </div>

                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor="paper_max_size_mb">
                            Maximum file size (MB)
                        </Label>
                        <Input
                            id="paper_max_size_mb"
                            type="number"
                            min={1}
                            max={100}
                            required
                            value={form.data.paper_max_size_mb}
                            onChange={(event) =>
                                form.setData(
                                    'paper_max_size_mb',
                                    event.target.value,
                                )
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Applies to the paper and the statement letter.
                        </p>
                        <InputError message={form.errors.paper_max_size_mb} />
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={form.data.require_statement_letter}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'require_statement_letter',
                                    checked === true,
                                )
                            }
                        />
                        Require a Statement Letter with every paper
                    </label>
                </SettingsSection>

                <SettingsSection
                    title="Terms & Conditions"
                    description="One statement per line. Participants must accept these in the last sign-up step."
                    defaultOpen
                >
                    <div className="grid gap-2">
                        <Label htmlFor="terms_organization">
                            Organization categories
                        </Label>
                        <Textarea
                            id="terms_organization"
                            rows={8}
                            required
                            value={form.data.terms_organization}
                            onChange={(event) =>
                                form.setData(
                                    'terms_organization',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.terms_organization} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="terms_individual">
                            Individual categories (Sustainability Leader)
                        </Label>
                        <Textarea
                            id="terms_individual"
                            rows={8}
                            required
                            value={form.data.terms_individual}
                            onChange={(event) =>
                                form.setData(
                                    'terms_individual',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={form.errors.terms_individual} />
                    </div>
                </SettingsSection>

                <SaveButton processing={form.processing} />
            </form>
        </>
    );
}

FileSettings.layout = {
    breadcrumbs: [
        { title: 'Admin Overview', href: dashboard() },
        { title: 'Files & Terms', href: edit() },
    ],
};
