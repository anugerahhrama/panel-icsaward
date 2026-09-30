import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AwardController from '@/actions/App/Http/Controllers/Admin/AwardController';
import {
    type Award,
    AWARD_LABELS,
    AWARDS,
} from '@/components/admin/score-recap/award-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export type AwardCandidate = {
    id: number;
    rank: number | null;
    name: string;
    initiative_title: string;
    stage1_score: number | null;
    stage2_score: number | null;
    score: number | null;
    suggested_award: Award | null;
    award: Award | null;
};

export type AwardQuota = Record<Award, number>;

const NONE = 'none';

function formatScore(value: number | null): string {
    return value === null ? '—' : value.toFixed(2);
}

/**
 * The award each finalist's final rank suggests. When a tie gives an award to more finalists than its quota, the tied
 * finalists get no suggestion and the committee decides.
 */
function suggestedAwards(
    candidates: AwardCandidate[],
    quota: AwardQuota,
): { awards: Record<number, Award>; hasTie: boolean } {
    const awards: Record<number, Award> = {};
    let hasTie = false;

    for (const award of AWARDS) {
        const suggested = candidates.filter(
            (candidate) => candidate.suggested_award === award,
        );

        if (suggested.length <= quota[award]) {
            suggested.forEach((candidate) => (awards[candidate.id] = award));
            continue;
        }

        hasTie = true;
        const tiedRank = suggested[quota[award]].rank;

        suggested
            .filter((candidate) => candidate.rank !== tiedRank)
            .forEach((candidate) => (awards[candidate.id] = award));
    }

    return { awards, hasTie };
}

function AwardsForm({
    categoryId,
    categoryName,
    candidates,
    quota,
    onConfirmed,
}: {
    categoryId: number;
    categoryName: string;
    candidates: AwardCandidate[];
    quota: AwardQuota;
    onConfirmed: () => void;
}) {
    const [suggestion] = useState(() => suggestedAwards(candidates, quota));
    const [awards, setAwards] = useState<Record<number, Award>>(
        suggestion.awards,
    );
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const given = (award: Award) =>
        Object.values(awards).filter((value) => value === award).length;
    const overQuota = AWARDS.filter((award) => given(award) > quota[award]);
    const awardedCount = Object.keys(awards).length;

    const setAward = (id: number, value: string) =>
        setAwards((current) => {
            const next = { ...current };

            if (value === NONE) {
                delete next[id];
            } else {
                next[id] = value as Award;
            }

            return next;
        });

    const confirm = () => {
        setProcessing(true);

        router.post(
            AwardController.store(categoryId).url,
            {
                awards: Object.entries(awards).map(([id, award]) => ({
                    submission_id: Number(id),
                    award,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setConfirming(false);
                    onConfirmed();
                },
                onError: (errors) =>
                    toast.error(
                        errors.awards ?? 'Failed to confirm the awards.',
                    ),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
                {suggestion.hasTie && (
                    <Alert>
                        <TriangleAlert />
                        <AlertDescription>
                            Finalists are tied on their final score, which would
                            exceed an award's quota. Choose the award for the
                            tied finalists.
                        </AlertDescription>
                    </Alert>
                )}

                {candidates.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        This category has no finalists.
                    </p>
                ) : (
                    <ul className="grid gap-2">
                        {candidates.map((candidate) => {
                            const award = awards[candidate.id];

                            return (
                                <li
                                    key={candidate.id}
                                    className={cn(
                                        'flex items-start gap-3 rounded-md border p-3',
                                        award && 'border-brand',
                                        candidate.rank === null && 'opacity-60',
                                    )}
                                >
                                    <span className="w-8 shrink-0 font-semibold tabular-nums">
                                        {candidate.rank ?? '—'}
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span className="font-medium">
                                            {candidate.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {candidate.initiative_title}
                                        </span>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {candidate.rank === null
                                                ? 'No final score yet'
                                                : `Final ${formatScore(candidate.score)} · Stage 1 ${formatScore(candidate.stage1_score)} · Stage 2 ${formatScore(candidate.stage2_score)}`}
                                        </span>
                                    </span>
                                    <Select
                                        value={award ?? NONE}
                                        disabled={candidate.rank === null}
                                        onValueChange={(value) =>
                                            setAward(candidate.id, value)
                                        }
                                    >
                                        <SelectTrigger
                                            size="sm"
                                            className="w-28"
                                            aria-label={`Award for ${candidate.name}`}
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                No award
                                            </SelectItem>
                                            {AWARDS.map((option) => (
                                                <SelectItem
                                                    key={option}
                                                    value={option}
                                                >
                                                    {AWARD_LABELS[option]}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <SheetFooter className="flex-row items-center justify-between border-t">
                <span className="flex flex-wrap gap-x-3 text-sm tabular-nums">
                    {AWARDS.map((award) => (
                        <span
                            key={award}
                            className={cn(
                                overQuota.includes(award)
                                    ? 'text-destructive'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {AWARD_LABELS[award]} {given(award)}/{quota[award]}
                        </span>
                    ))}
                </span>
                <Button
                    disabled={awardedCount === 0 || overQuota.length > 0}
                    onClick={() => setConfirming(true)}
                >
                    Confirm awards
                </Button>
            </SheetFooter>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogTitle>Confirm awards?</DialogTitle>
                    <DialogDescription>
                        {awardedCount} {awardedCount === 1 ? 'award' : 'awards'}{' '}
                        in <strong>{categoryName}</strong> are confirmed and the
                        category's pitching scores and final results are frozen.
                        Only a superadmin can reopen them.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button disabled={processing} onClick={confirm}>
                            {processing && <Spinner />}
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

export function AwardsSheet({
    category,
    candidates,
    quota,
    open,
    onOpenChange,
}: {
    category: { id: number; name: string };
    candidates: AwardCandidate[];
    quota: AwardQuota;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Confirm awards</SheetTitle>
                    <SheetDescription>
                        {category.name}. Up to {quota.gold} Gold, {quota.silver}{' '}
                        Silver and {quota.bronze} Bronze; the final rank
                        suggests them.
                    </SheetDescription>
                </SheetHeader>
                {open && (
                    <AwardsForm
                        key={category.id}
                        categoryId={category.id}
                        categoryName={category.name}
                        candidates={candidates}
                        quota={quota}
                        onConfirmed={() => onOpenChange(false)}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}
