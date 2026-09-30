import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import FinalistController from '@/actions/App/Http/Controllers/Admin/FinalistController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
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

export type FinalistCandidate = {
    id: number;
    rank: number | null;
    name: string;
    initiative_title: string;
    score: number | null;
    judges_submitted: number | null;
    judges_assigned: number | null;
    is_finalist: boolean;
};

function isIncomplete(candidate: FinalistCandidate): boolean {
    return (
        candidate.judges_submitted !== null &&
        candidate.judges_assigned !== null &&
        candidate.judges_submitted < candidate.judges_assigned
    );
}

/**
 * Rank 1–max is suggested. When a tie at the cut-off would exceed the quota, only the ranks above the tie are
 * suggested and the committee picks from the tied submissions.
 */
function suggestedSelection(
    candidates: FinalistCandidate[],
    max: number,
): { ids: number[]; tiedRank: number | null } {
    const ranked = candidates.filter(
        (candidate) => candidate.rank !== null && candidate.rank <= max,
    );

    if (ranked.length <= max) {
        return { ids: ranked.map((candidate) => candidate.id), tiedRank: null };
    }

    const tiedRank = ranked[max].rank as number;

    return {
        ids: ranked
            .filter((candidate) => (candidate.rank as number) < tiedRank)
            .map((candidate) => candidate.id),
        tiedRank,
    };
}

function FinalistsForm({
    categoryId,
    categoryName,
    candidates,
    max,
    onConfirmed,
}: {
    categoryId: number;
    categoryName: string;
    candidates: FinalistCandidate[];
    max: number;
    onConfirmed: () => void;
}) {
    const [suggestion] = useState(() => suggestedSelection(candidates, max));
    const [selected, setSelected] = useState<number[]>(suggestion.ids);
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    const isFull = selected.length >= max;
    const incompleteCount = candidates.filter(
        (candidate) =>
            selected.includes(candidate.id) && isIncomplete(candidate),
    ).length;

    const toggle = (id: number, checked: boolean) =>
        setSelected((current) =>
            checked
                ? [...current, id]
                : current.filter((selectedId) => selectedId !== id),
        );

    const confirm = () => {
        setProcessing(true);

        router.post(
            FinalistController.store(categoryId).url,
            { submission_ids: selected },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setConfirming(false);
                    onConfirmed();
                },
                onError: (errors) =>
                    toast.error(
                        errors.submission_ids ??
                            'Failed to confirm the finalists.',
                    ),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
                {suggestion.tiedRank !== null && (
                    <Alert>
                        <TriangleAlert />
                        <AlertDescription>
                            Submissions are tied at rank {suggestion.tiedRank},
                            which would exceed {max} finalists. Choose which of
                            them fill the remaining spots.
                        </AlertDescription>
                    </Alert>
                )}

                {candidates.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        This category has no qualified submissions.
                    </p>
                ) : (
                    <ul className="grid gap-2">
                        {candidates.map((candidate) => {
                            const isSelected = selected.includes(candidate.id);
                            const isDisabled =
                                candidate.rank === null ||
                                (isFull && !isSelected);

                            return (
                                <li key={candidate.id}>
                                    <label
                                        className={cn(
                                            'flex items-start gap-3 rounded-md border p-3',
                                            isSelected && 'border-brand',
                                            isDisabled
                                                ? 'opacity-60'
                                                : 'cursor-pointer',
                                        )}
                                    >
                                        <Checkbox
                                            checked={isSelected}
                                            disabled={isDisabled}
                                            onCheckedChange={(checked) =>
                                                toggle(
                                                    candidate.id,
                                                    checked === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
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
                                            {isIncomplete(candidate) && (
                                                <Badge
                                                    variant="outline"
                                                    className="mt-1"
                                                >
                                                    Incomplete ·{' '}
                                                    {candidate.judges_submitted}
                                                    /{candidate.judges_assigned}{' '}
                                                    judges
                                                </Badge>
                                            )}
                                            {candidate.rank === null && (
                                                <span className="text-xs text-muted-foreground">
                                                    Not scored yet
                                                </span>
                                            )}
                                        </span>
                                        <span className="font-medium tabular-nums">
                                            {candidate.score === null
                                                ? '—'
                                                : candidate.score.toFixed(2)}
                                        </span>
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            <SheetFooter className="flex-row items-center justify-between border-t">
                <span className="text-sm text-muted-foreground tabular-nums">
                    {selected.length} / {max} selected
                </span>
                <Button
                    disabled={selected.length === 0}
                    onClick={() => setConfirming(true)}
                >
                    Confirm finalists
                </Button>
            </SheetFooter>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogTitle>Confirm finalists?</DialogTitle>
                    <DialogDescription>
                        {selected.length}{' '}
                        {selected.length === 1 ? 'submission' : 'submissions'}{' '}
                        in <strong>{categoryName}</strong> become finalists and
                        the category's Stage 1 results are frozen. Participants
                        see Finalist on their dashboard right away. Only a
                        superadmin can reopen them.
                    </DialogDescription>
                    {incompleteCount > 0 && (
                        <Alert variant="destructive">
                            <TriangleAlert />
                            <AlertDescription>
                                {incompleteCount} selected{' '}
                                {incompleteCount === 1
                                    ? 'submission is'
                                    : 'submissions are'}{' '}
                                not scored by every assigned judge.
                            </AlertDescription>
                        </Alert>
                    )}
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

export function FinalistsSheet({
    category,
    candidates,
    max,
    open,
    onOpenChange,
}: {
    category: { id: number; name: string };
    candidates: FinalistCandidate[];
    max: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Select finalists</SheetTitle>
                    <SheetDescription>
                        {category.name}. Up to {max} finalists; the top {max}{' '}
                        ranks are suggested.
                    </SheetDescription>
                </SheetHeader>
                {open && (
                    <FinalistsForm
                        key={category.id}
                        categoryId={category.id}
                        categoryName={category.name}
                        candidates={candidates}
                        max={max}
                        onConfirmed={() => onOpenChange(false)}
                    />
                )}
            </SheetContent>
        </Sheet>
    );
}
