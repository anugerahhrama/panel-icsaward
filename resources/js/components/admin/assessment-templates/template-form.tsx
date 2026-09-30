import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import { toast } from 'sonner';
import { DisabledTooltip } from '@/components/admin/disabled-tooltip';
import { SaveButton } from '@/components/admin/save-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { TOTAL_WEIGHT } from '@/pages/admin/assessment-templates/columns';
import { store, update } from '@/routes/admin/assessment-templates';

export type TemplateDetails = {
    id: number;
    name: string;
    description: string | null;
};

export type CriterionRow = {
    id: number;
    aspect: string;
    criteria: string;
    description: string | null;
    weight: number;
    has_scores: boolean;
};

type CriterionField = {
    key: number;
    id: number | null;
    aspect: string;
    criteria: string;
    description: string;
    weight: number | '';
    hasScores: boolean;
};

type TemplateFormProps = {
    template?: TemplateDetails;
    criteria?: CriterionRow[];
    readOnly?: boolean;
};

export function TemplateForm({
    template,
    criteria = [],
    readOnly = false,
}: TemplateFormProps) {
    const nextKey = useRef(criteria.length);

    const emptyCriterion = (): CriterionField => ({
        key: nextKey.current++,
        id: null,
        aspect: '',
        criteria: '',
        description: '',
        weight: '',
        hasScores: false,
    });

    const { data, setData, post, put, processing, errors, transform } = useForm(
        {
            name: template?.name ?? '',
            description: template?.description ?? '',
            criteria:
                criteria.length > 0
                    ? criteria.map((criterion, index): CriterionField => ({
                          key: index,
                          id: criterion.id,
                          aspect: criterion.aspect,
                          criteria: criterion.criteria,
                          description: criterion.description ?? '',
                          weight: criterion.weight,
                          hasScores: criterion.has_scores,
                      }))
                    : [emptyCriterion()],
        },
    );

    const fieldErrors = errors as Record<string, string | undefined>;

    const totalWeight = data.criteria.reduce(
        (total, criterion) => total + (Number(criterion.weight) || 0),
        0,
    );
    const isBalanced = totalWeight === TOTAL_WEIGHT;

    const updateCriterion = (index: number, changes: Partial<CriterionField>) =>
        setData(
            'criteria',
            data.criteria.map((criterion, position) =>
                position === index ? { ...criterion, ...changes } : criterion,
            ),
        );

    const moveCriterion = (index: number, offset: number) => {
        const reordered = [...data.criteria];
        const [moved] = reordered.splice(index, 1);
        reordered.splice(index + offset, 0, moved);
        setData('criteria', reordered);
    };

    const removeCriterion = (index: number) =>
        setData(
            'criteria',
            data.criteria.filter((_, position) => position !== index),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        transform((form) => ({
            ...form,
            criteria: form.criteria.map(
                ({ key: _key, hasScores: _hasScores, id, ...criterion }) =>
                    id === null ? criterion : { id, ...criterion },
            ),
        }));

        const options = {
            preserveScroll: true,
            onError: () =>
                toast.error('Failed to save template. Check the fields below.'),
        };

        if (template) {
            put(update(template.id).url, options);
        } else {
            post(store().url, options);
        }
    };

    return (
        <form onSubmit={submit}>
            <fieldset disabled={readOnly} className="space-y-6">
                <div className="grid max-w-2xl gap-4">
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
                            rows={3}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                        <InputError message={errors.description} />
                    </div>
                </div>

                <div className="space-y-3">
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <h3 className="text-base font-medium">
                                Assessment aspects
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Judges score each aspect from 0 to 100. Weights
                                must add up to {TOTAL_WEIGHT}%.
                            </p>
                        </div>
                        <p
                            className={cn(
                                'shrink-0 text-sm font-medium tabular-nums',
                                isBalanced
                                    ? 'text-muted-foreground'
                                    : 'text-destructive',
                            )}
                            aria-live="polite"
                        >
                            Total weight: {totalWeight} / {TOTAL_WEIGHT}%
                        </p>
                    </div>

                    <div className="hidden gap-3 px-3 text-xs font-medium text-muted-foreground lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,2fr)_6rem_6.5rem]">
                        <span>Aspect</span>
                        <span>Criteria</span>
                        <span>Description & key indicators</span>
                        <span>Weight (%)</span>
                        <span className="sr-only">Actions</span>
                    </div>

                    <ol className="space-y-3">
                        {data.criteria.map((criterion, index) => (
                            <li
                                key={criterion.key}
                                className="grid gap-3 rounded-md border p-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,2fr)_6rem_6.5rem] lg:items-start"
                            >
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`criteria-${index}-aspect`}
                                        className="lg:sr-only"
                                    >
                                        Aspect
                                    </Label>
                                    <Textarea
                                        id={`criteria-${index}-aspect`}
                                        value={criterion.aspect}
                                        maxLength={255}
                                        rows={3}
                                        required
                                        onChange={(e) =>
                                            updateCriterion(index, {
                                                aspect: e.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                `criteria.${index}.aspect`
                                            ]
                                        }
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`criteria-${index}-criteria`}
                                        className="lg:sr-only"
                                    >
                                        Criteria
                                    </Label>
                                    <Textarea
                                        id={`criteria-${index}-criteria`}
                                        value={criterion.criteria}
                                        maxLength={2000}
                                        rows={3}
                                        required
                                        onChange={(e) =>
                                            updateCriterion(index, {
                                                criteria: e.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                `criteria.${index}.criteria`
                                            ]
                                        }
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`criteria-${index}-description`}
                                        className="lg:sr-only"
                                    >
                                        Description & key indicators
                                    </Label>
                                    <Textarea
                                        id={`criteria-${index}-description`}
                                        value={criterion.description}
                                        maxLength={5000}
                                        rows={3}
                                        onChange={(e) =>
                                            updateCriterion(index, {
                                                description: e.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                `criteria.${index}.description`
                                            ]
                                        }
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`criteria-${index}-weight`}
                                        className="lg:sr-only"
                                    >
                                        Weight (%)
                                    </Label>
                                    <Input
                                        id={`criteria-${index}-weight`}
                                        type="number"
                                        min={1}
                                        max={TOTAL_WEIGHT}
                                        required
                                        value={criterion.weight}
                                        onChange={(e) =>
                                            updateCriterion(index, {
                                                weight:
                                                    e.target.value === ''
                                                        ? ''
                                                        : Number(
                                                              e.target.value,
                                                          ),
                                            })
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                `criteria.${index}.weight`
                                            ] ??
                                            fieldErrors[`criteria.${index}.id`]
                                        }
                                    />
                                </div>

                                <div className="flex gap-1 lg:justify-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={index === 0}
                                        onClick={() => moveCriterion(index, -1)}
                                        aria-label="Move aspect up"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={
                                            index === data.criteria.length - 1
                                        }
                                        onClick={() => moveCriterion(index, 1)}
                                        aria-label="Move aspect down"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <DisabledTooltip
                                        reason={
                                            criterion.hasScores && !readOnly
                                                ? 'Judges have scored this aspect, so it cannot be removed.'
                                                : null
                                        }
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={
                                                data.criteria.length === 1 ||
                                                criterion.hasScores
                                            }
                                            onClick={() =>
                                                removeCriterion(index)
                                            }
                                            aria-label="Remove aspect"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </DisabledTooltip>
                                </div>
                            </li>
                        ))}
                    </ol>

                    <InputError message={errors.criteria} />

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            setData('criteria', [
                                ...data.criteria,
                                emptyCriterion(),
                            ])
                        }
                    >
                        <Plus />
                        Add Assessment Aspect
                    </Button>
                </div>

                {!readOnly && (
                    <SaveButton
                        processing={processing}
                        disabled={!isBalanced}
                    />
                )}
            </fieldset>
        </form>
    );
}
