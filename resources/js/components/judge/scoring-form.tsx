import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { update } from '@/routes/judge/submissions/scores';

export type ScoringCriterion = {
    id: number;
    aspect: string;
    criteria: string;
    description: string | null;
    weight: number;
    raw_score: number | null;
    notes: string | null;
};

type ScoreField = {
    criterion_id: number;
    raw_score: string;
    notes: string;
};

type Action = 'draft' | 'submit';

/**
 * Weighted total on a 0–100 scale: Σ (raw score × weight / 100) over the scored criteria.
 */
function weightedTotal(
    criteria: ScoringCriterion[],
    scores: ScoreField[],
): number {
    return criteria.reduce((total, criterion, index) => {
        const raw = scores[index].raw_score;

        return raw === ''
            ? total
            : total + (Number(raw) * criterion.weight) / 100;
    }, 0);
}

export function ScoringForm({
    submissionUuid,
    criteria,
    isSubmitted,
    isScoringOpen,
}: {
    submissionUuid: string;
    criteria: ScoringCriterion[];
    isSubmitted: boolean;
    isScoringOpen: boolean;
}) {
    const { data, setData, put, processing, errors, transform } = useForm<{
        scores: ScoreField[];
    }>({
        scores: criteria.map((criterion) => ({
            criterion_id: criterion.id,
            raw_score: criterion.raw_score?.toString() ?? '',
            notes: criterion.notes ?? '',
        })),
    });

    const fieldErrors = errors as Record<string, string | undefined>;
    const readOnly = !isScoringOpen;
    const scoredCount = data.scores.filter(
        (score) => score.raw_score !== '',
    ).length;
    const total = weightedTotal(criteria, data.scores);

    const updateScore = (index: number, changes: Partial<ScoreField>) =>
        setData(
            'scores',
            data.scores.map((score, position) =>
                position === index ? { ...score, ...changes } : score,
            ),
        );

    const save = (action: Action) => {
        transform((form) => ({
            action,
            scores: form.scores.map((score) => ({
                criterion_id: score.criterion_id,
                raw_score:
                    score.raw_score === '' ? null : Number(score.raw_score),
                notes: score.notes,
            })),
        }));

        put(update(submissionUuid).url, {
            preserveScroll: true,
            onError: () =>
                toast.error('Scores were not saved. Check the fields below.'),
        });
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                save('submit');
            }}
            className="grid gap-4"
        >
            {criteria.map((criterion, index) => (
                <Card key={criterion.id}>
                    <CardHeader className="flex-row items-start justify-between gap-4">
                        <div className="grid gap-1">
                            <CardTitle className="text-base">
                                {index + 1}. {criterion.aspect}
                            </CardTitle>
                            <p className="text-sm font-medium text-muted-foreground">
                                {criterion.criteria}
                            </p>
                        </div>
                        <Badge variant="secondary" className="shrink-0">
                            Weight {criterion.weight}%
                        </Badge>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {criterion.description && (
                            <p className="text-sm whitespace-pre-line text-muted-foreground">
                                {criterion.description}
                            </p>
                        )}
                        <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                            <div className="grid content-start gap-2">
                                <Label htmlFor={`score-${criterion.id}`}>
                                    Score (0–100)
                                </Label>
                                <Input
                                    id={`score-${criterion.id}`}
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={100}
                                    step={1}
                                    value={data.scores[index].raw_score}
                                    onChange={(event) =>
                                        updateScore(index, {
                                            raw_score: event.target.value,
                                        })
                                    }
                                    disabled={readOnly}
                                    className="text-lg font-semibold tabular-nums"
                                />
                                <InputError
                                    message={
                                        fieldErrors[`scores.${index}.raw_score`]
                                    }
                                />
                            </div>
                            <div className="grid content-start gap-2">
                                <Label htmlFor={`notes-${criterion.id}`}>
                                    Notes{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Textarea
                                    id={`notes-${criterion.id}`}
                                    rows={3}
                                    maxLength={2000}
                                    value={data.scores[index].notes}
                                    onChange={(event) =>
                                        updateScore(index, {
                                            notes: event.target.value,
                                        })
                                    }
                                    disabled={readOnly}
                                />
                                <InputError
                                    message={
                                        fieldErrors[`scores.${index}.notes`]
                                    }
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            ))}

            <div className="sticky bottom-0 z-10 flex flex-col gap-3 rounded-xl border bg-background/95 p-4 shadow-sm backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                <div className="grid gap-0.5">
                    <p className="text-sm text-muted-foreground">
                        Weighted total · {scoredCount} of {criteria.length}{' '}
                        criteria scored
                    </p>
                    <p className="text-2xl font-semibold tabular-nums">
                        {total.toFixed(2)}
                        <span className="text-base text-muted-foreground">
                            {' '}
                            / 100
                        </span>
                    </p>
                    <InputError message={errors.scores} />
                </div>
                {!readOnly && (
                    <div className="flex flex-wrap justify-end gap-2">
                        {!isSubmitted && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => save('draft')}
                            >
                                Save draft
                            </Button>
                        )}
                        <Button
                            type="submit"
                            disabled={
                                processing || scoredCount < criteria.length
                            }
                        >
                            {processing && <Spinner />}
                            {isSubmitted ? 'Update scores' : 'Submit scores'}
                        </Button>
                    </div>
                )}
            </div>
        </form>
    );
}
