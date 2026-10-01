import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    FilePreviewButton,
    type SubmittedFile,
} from '@/components/submissions/file-preview';
import { Detail } from '@/components/admin/participants/detail';
import { SubmissionStatusBadge } from '@/components/admin/participants/submission-status-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDateTimeWib, todayWib } from '@/lib/datetime';
import type { PaperRow } from '@/pages/admin/participants/papers-columns';
import { update } from '@/routes/admin/participants/verification';

type Decision = 'qualified' | 'needs_revision' | 'disqualified';

const DECISIONS: { value: Decision; label: string }[] = [
    { value: 'qualified', label: 'Qualified' },
    { value: 'needs_revision', label: 'Needs revision' },
    { value: 'disqualified', label: 'Disqualified' },
];

function VerificationForm({
    submission,
    onSaved,
}: {
    submission: PaperRow;
    onSaved: () => void;
}) {
    const { data, setData, put, processing, errors } = useForm<{
        decision: Decision | '';
        revision_note: string;
        revision_deadline: string;
        disqualified_reason: string;
    }>({
        decision: '',
        revision_note: '',
        revision_deadline: '',
        disqualified_reason: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        put(update(submission.uuid).url, {
            preserveScroll: true,
            onSuccess: () => onSaved(),
            onError: (formErrors) =>
                toast.error(
                    formErrors.decision ??
                        'Failed to save the decision. Check the fields below.',
                ),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-2">
                <Label>Decision</Label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    className="w-full"
                    value={data.decision}
                    onValueChange={(value) =>
                        setData('decision', value as Decision | '')
                    }
                >
                    {DECISIONS.map((decision) => (
                        <ToggleGroupItem
                            key={decision.value}
                            value={decision.value}
                            className="flex-1"
                        >
                            {decision.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                <p className="text-xs text-muted-foreground">
                    The participant is emailed the decision. It cannot be
                    changed afterwards.
                </p>
                <InputError message={errors.decision} />
            </div>

            {data.decision === 'needs_revision' && (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="revision_note">Revision note</Label>
                        <Textarea
                            id="revision_note"
                            value={data.revision_note}
                            maxLength={5000}
                            rows={6}
                            required
                            onChange={(e) =>
                                setData('revision_note', e.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Shown to the participant on their dashboard and in
                            the email.
                        </p>
                        <InputError message={errors.revision_note} />
                    </div>

                    <div className="grid max-w-xs gap-2">
                        <Label htmlFor="revision_deadline">
                            Revision deadline
                        </Label>
                        <Input
                            id="revision_deadline"
                            type="date"
                            min={todayWib()}
                            required
                            value={data.revision_deadline}
                            onChange={(e) =>
                                setData('revision_deadline', e.target.value)
                            }
                        />
                        <p className="text-xs text-muted-foreground">
                            Revisions are accepted until 23:59 WIB on this day.
                        </p>
                        <InputError message={errors.revision_deadline} />
                    </div>
                </>
            )}

            {data.decision === 'disqualified' && (
                <div className="grid gap-2">
                    <Label htmlFor="disqualified_reason">Reason</Label>
                    <Textarea
                        id="disqualified_reason"
                        value={data.disqualified_reason}
                        maxLength={5000}
                        rows={6}
                        required
                        onChange={(e) =>
                            setData('disqualified_reason', e.target.value)
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        Shown to the participant on their dashboard and in the
                        email.
                    </p>
                    <InputError message={errors.disqualified_reason} />
                </div>
            )}

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !data.decision}>
                    {processing && <Spinner />}
                    Save decision
                </Button>
            </div>
        </form>
    );
}

function SubmittedFiles({
    submission,
    onPreview,
}: {
    submission: PaperRow;
    onPreview: (file: SubmittedFile) => void;
}) {
    return (
        <dl className="mb-6 grid gap-4 sm:grid-cols-2">
            <Detail
                label="Paper"
                value={
                    <FilePreviewButton
                        file={submission.paper}
                        onPreview={onPreview}
                    />
                }
            />
            <Detail
                label="Statement letter"
                value={
                    <FilePreviewButton
                        file={submission.statement}
                        onPreview={onPreview}
                    />
                }
            />
        </dl>
    );
}

function VerificationDetails({ submission }: { submission: PaperRow }) {
    const { verification } = submission;

    if (!verification.reviewed_at) {
        return (
            <p className="text-sm text-muted-foreground">
                This submission has no verification decision yet.
            </p>
        );
    }

    return (
        <dl className="grid gap-4">
            <Detail
                label="Decision"
                value={<SubmissionStatusBadge status={submission.status} />}
            />
            {verification.revision_note && (
                <Detail
                    label="Revision note"
                    value={verification.revision_note}
                />
            )}
            {verification.revision_deadline && (
                <Detail
                    label="Revision deadline"
                    value={formatDateTimeWib(verification.revision_deadline)}
                />
            )}
            {verification.disqualified_reason && (
                <Detail
                    label="Reason"
                    value={verification.disqualified_reason}
                />
            )}
            <Detail
                label="Reviewed"
                value={`${formatDateTimeWib(verification.reviewed_at)}${verification.reviewer ? ` by ${verification.reviewer}` : ''}`}
            />
            <Detail
                label="Participant email"
                value={
                    verification.notified_at
                        ? `Sent ${formatDateTimeWib(verification.notified_at)}`
                        : 'Pending'
                }
            />
        </dl>
    );
}

export function VerificationSheet({
    submission,
    open,
    onOpenChange,
    onPreview,
}: {
    submission: PaperRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPreview: (file: SubmittedFile) => void;
}) {
    const isReviewable = submission?.status === 'under_review';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>
                        {isReviewable
                            ? 'Administrative verification'
                            : 'Verification decision'}
                    </SheetTitle>
                    <SheetDescription>
                        {submission
                            ? `${submission.initiative_title} · ${submission.name}`
                            : ''}
                    </SheetDescription>
                </SheetHeader>
                {submission && (
                    <div className="px-4 pb-4">
                        <SubmittedFiles
                            submission={submission}
                            onPreview={onPreview}
                        />
                        {isReviewable ? (
                            <VerificationForm
                                key={submission.id}
                                submission={submission}
                                onSaved={() => onOpenChange(false)}
                            />
                        ) : (
                            <VerificationDetails submission={submission} />
                        )}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
