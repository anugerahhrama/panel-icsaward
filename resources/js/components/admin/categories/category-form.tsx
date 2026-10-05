import { Link, useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import { LogoUploadField } from '@/components/admin/logo-upload-field';
import { SaveButton } from '@/components/admin/save-button';
import InputError from '@/components/input-error';
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
import {
    APPLICANT_TYPE_LABELS,
    type ApplicantType,
    type CategoryRow,
} from '@/pages/admin/categories/columns';
import { index as templatesIndex } from '@/routes/admin/assessment-templates';
import { store, update } from '@/routes/admin/categories';

export type TemplateOption = {
    id: number;
    name: string;
};

const NO_TEMPLATE = 'none';

type CategoryFormProps = {
    category?: CategoryRow;
    templates: TemplateOption[];
    nextSortOrder: number;
    onSaved?: () => void;
};

export function CategoryForm({
    category,
    templates,
    nextSortOrder,
    onSaved,
}: CategoryFormProps) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        description: string;
        applicant_type: ApplicantType;
        assessment_template_id: number | null;
        sort_order: number;
        paper_template: File | null;
        remove_paper_template: boolean;
    }>({
        name: category?.name ?? '',
        description: category?.description ?? '',
        applicant_type: category?.applicant_type ?? 'organization',
        assessment_template_id: category?.assessment_template_id ?? null,
        sort_order: category?.sort_order ?? nextSortOrder,
        paper_template: null,
        remove_paper_template: false,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
            onError: () =>
                toast.error('Failed to save category. Check the fields below.'),
        };

        post(category ? update(category.id).url : store().url, options);
    };

    return (
        <form onSubmit={submit} className="space-y-4">
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

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                    id="description"
                    value={data.description}
                    maxLength={2000}
                    rows={4}
                    onChange={(e) => setData('description', e.target.value)}
                />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="applicant_type">Applicant type</Label>
                <Select
                    value={data.applicant_type}
                    onValueChange={(value) =>
                        setData('applicant_type', value as ApplicantType)
                    }
                >
                    <SelectTrigger id="applicant_type" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(APPLICANT_TYPE_LABELS).map(
                            ([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    Decides which terms participants accept when signing up.
                </p>
                <InputError message={errors.applicant_type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="assessment_template_id">
                    Assessment template
                </Label>
                <Select
                    value={
                        data.assessment_template_id === null
                            ? NO_TEMPLATE
                            : String(data.assessment_template_id)
                    }
                    disabled={category?.has_scores}
                    onValueChange={(value) =>
                        setData(
                            'assessment_template_id',
                            value === NO_TEMPLATE ? null : Number(value),
                        )
                    }
                >
                    <SelectTrigger
                        id="assessment_template_id"
                        className="w-full"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NO_TEMPLATE}>None</SelectItem>
                        {templates.map((template) => (
                            <SelectItem
                                key={template.id}
                                value={String(template.id)}
                            >
                                {template.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    {category?.has_scores &&
                        'Judges have already scored this category, so its template cannot be changed. '}
                    The rubric judges use for this category. Manage rubrics
                    under{' '}
                    <Link
                        href={templatesIndex()}
                        className="underline underline-offset-4"
                    >
                        Assessment Templates
                    </Link>
                    .
                </p>
                <InputError message={errors.assessment_template_id} />
            </div>

            <div className="grid gap-2">
                <LogoUploadField
                    label="Paper template"
                    accept=".pdf,.doc,.docx,.pptx"
                    hint="Click to choose a PDF, Word, or PowerPoint file"
                    currentUrl={
                        data.remove_paper_template
                            ? null
                            : (category?.paper_template_url ?? null)
                    }
                    currentName={category?.paper_template_name ?? null}
                    file={data.paper_template}
                    onChange={(file) => setData('paper_template', file)}
                    onRemoveCurrent={() =>
                        setData('remove_paper_template', true)
                    }
                    error={errors.paper_template}
                />
                <p className="text-xs text-muted-foreground">
                    Participants in this category download this file. Without
                    one, the default template from Settings → Files is used.
                </p>
            </div>

            <div className="grid max-w-xs gap-2">
                <Label htmlFor="sort_order">Sort order</Label>
                <Input
                    id="sort_order"
                    type="number"
                    min={0}
                    max={65535}
                    required
                    value={data.sort_order}
                    onChange={(e) =>
                        setData('sort_order', Number(e.target.value))
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Lower numbers appear first on the sign-up form.
                </p>
                <InputError message={errors.sort_order} />
            </div>

            <SaveButton processing={processing} />
        </form>
    );
}
