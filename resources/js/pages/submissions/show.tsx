import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Clock, Download } from 'lucide-react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import { LogoUploadField } from '@/components/admin/logo-upload-field';
import { LockedFile } from '@/components/submissions/locked-file';
import { STATUS_LABELS } from '@/components/submissions/submission-status-card';
import type { SubmissionStatus } from '@/components/submissions/submission-status-card';
import { WhatsNext } from '@/components/submissions/whats-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTimeLeft } from '@/hooks/use-time-left';
import { formatDateTimeWib } from '@/lib/datetime';
import { FORM_ERROR_TOASTER_ID, toastFormErrors } from '@/lib/form-errors';
import { dashboard } from '@/routes';
import { store } from '@/routes/submissions/paper';

type Props = {
    submission: {
        uuid: string;
        initiativeTitle: string;
        category: string;
        paperTemplateUrl: string | null;
        status: SubmissionStatus;
        paperUploadedAt: string | null;
        paperOriginalName: string | null;
        paperUrl: string | null;
        statementOriginalName: string | null;
        statementUrl: string | null;
        revisionNote: string | null;
        revisionDeadline: string | null;
        isRevisionOpen: boolean;
    };
    requirements: {
        paperExtensions: string[];
        statementLetterExtensions: string[];
        maxSizeMb: number;
        requireStatementLetter: boolean;
    };
    paperDeadline: string | null;
    isClosed: boolean;
    statementLetterTemplateUrl: string | null;
    contactEmail: string | null;
};

type PaperFormData = {
    paper: File | null;
    statement_letter: File | null;
};

function acceptList(extensions: string[]): string {
    return extensions.map((extension) => `.${extension}`).join(',');
}

function SubmissionSummary({
    submission,
}: {
    submission: Props['submission'];
}) {
    return (
        <div className="grid gap-1 rounded-lg border bg-muted/40 p-4 text-sm">
            <p className="text-xs font-semibold tracking-wide text-brand uppercase">
                {submission.category}
            </p>
            <p className="font-medium">{submission.initiativeTitle}</p>
        </div>
    );
}

function ContactLine({ contactEmail }: { contactEmail: string | null }) {
    if (!contactEmail) {
        return null;
    }

    return (
        <>
            {' '}
            If you have any questions, contact us at{' '}
            <a
                href={`mailto:${contactEmail}`}
                className="font-medium text-brand underline underline-offset-4"
            >
                {contactEmail}
            </a>
            .
        </>
    );
}

function SubmittedState({
    submission,
    contactEmail,
}: {
    submission: Props['submission'];
    contactEmail: string | null;
}) {
    const isRevisionClosed = submission.status === 'needs_revision';

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col items-center gap-2 text-center">
                {isRevisionClosed ? (
                    <Clock className="size-10 text-muted-foreground" />
                ) : (
                    <CircleCheck className="size-10 text-brand" />
                )}
                <h2 className="font-display text-lg font-bold">
                    {isRevisionClosed
                        ? 'Revision closed'
                        : 'Your paper has been submitted'}
                </h2>
                <p className="text-sm text-balance text-muted-foreground">
                    {isRevisionClosed ? (
                        <>
                            The revision deadline has passed, so uploads are no
                            longer accepted.
                            <ContactLine contactEmail={contactEmail} />
                        </>
                    ) : (
                        'Your files are locked while the committee reviews them.'
                    )}
                </p>
                <Badge variant="secondary">
                    {STATUS_LABELS[submission.status]}
                </Badge>
            </div>

            <SubmissionSummary submission={submission} />

            <div className="grid gap-2">
                {submission.paperOriginalName && (
                    <LockedFile
                        label="Submission paper"
                        name={submission.paperOriginalName}
                        href={submission.paperUrl}
                    />
                )}
                {submission.statementOriginalName && (
                    <LockedFile
                        label="Statement letter"
                        name={submission.statementOriginalName}
                        href={submission.statementUrl}
                    />
                )}
            </div>

            <WhatsNext />

            <Button variant="outline" asChild>
                <Link href={dashboard()}>Go to dashboard</Link>
            </Button>
        </div>
    );
}

function ClosedState({
    submission,
    contactEmail,
}: {
    submission: Props['submission'];
    contactEmail: string | null;
}) {
    return (
        <div className="flex flex-col gap-6">
            <SubmissionSummary submission={submission} />
            <div className="flex flex-col items-center gap-2 text-center">
                <Clock className="size-10 text-muted-foreground" />
                <h2 className="font-display text-lg font-bold">
                    Submission closed
                </h2>
                <p className="text-sm text-balance text-muted-foreground">
                    The paper submission deadline has passed, so uploads are no
                    longer accepted.
                    <ContactLine contactEmail={contactEmail} />
                </p>
            </div>
        </div>
    );
}

function RevisionNotes({ submission }: { submission: Props['submission'] }) {
    const timeLeft = useTimeLeft(submission.revisionDeadline);

    return (
        <div className="flex gap-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 text-sm">
            <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <div className="grid gap-3">
                <p className="font-medium">
                    The committee has asked you to revise your submission.
                </p>
                {submission.revisionNote && (
                    <div className="grid gap-1">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Notes from the committee
                        </p>
                        <p className="whitespace-pre-line">
                            {submission.revisionNote}
                        </p>
                    </div>
                )}
                {submission.revisionDeadline && (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-muted-foreground">
                            Revision deadline:{' '}
                            <span className="font-medium text-foreground">
                                {formatDateTimeWib(submission.revisionDeadline)}
                            </span>
                        </span>
                        {timeLeft && (
                            <Badge variant="secondary">{timeLeft}</Badge>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}

function PaperForm({
    mode,
    submission,
    requirements,
    paperDeadline,
    statementLetterTemplateUrl,
}: {
    mode: 'initial' | 'revision';
    submission: Props['submission'];
    requirements: Props['requirements'];
    paperDeadline: string | null;
    statementLetterTemplateUrl: string | null;
}) {
    const isRevision = mode === 'revision';
    const form = useForm<PaperFormData>({
        paper: null,
        statement_letter: null,
    });
    const timeLeft = useTimeLeft(isRevision ? null : paperDeadline);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(store.url(submission.uuid), {
            forceFormData: true,
            onSuccess: () =>
                toast.success(
                    isRevision
                        ? 'Your revised files have been submitted.'
                        : 'Your paper has been submitted.',
                    { toasterId: FORM_ERROR_TOASTER_ID },
                ),
            onError: (errors) => toastFormErrors(errors),
        });
    }

    const paperFormats = requirements.paperExtensions
        .map((extension) => extension.toUpperCase())
        .join(', ');
    const statementFormats = requirements.statementLetterExtensions
        .map((extension) => extension.toUpperCase())
        .join(', ');
    const keepCurrentHint = isRevision
        ? ' · leave empty to keep the current file'
        : '';
    const showStatementLetter =
        requirements.requireStatementLetter ||
        (isRevision && submission.statementUrl !== null);

    return (
        <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
            <SubmissionSummary submission={submission} />

            {isRevision ? (
                <RevisionNotes submission={submission} />
            ) : (
                paperDeadline && (
                    <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <p>
                            <span className="text-muted-foreground">
                                Deadline:{' '}
                            </span>
                            <span className="font-medium">
                                {formatDateTimeWib(paperDeadline)}
                            </span>
                        </p>
                        {timeLeft && (
                            <Badge variant="secondary">{timeLeft}</Badge>
                        )}
                    </div>
                )
            )}

            {(submission.paperTemplateUrl || statementLetterTemplateUrl) && (
                <div className="flex flex-wrap gap-2">
                    {submission.paperTemplateUrl && (
                        <Button variant="outline" size="sm" asChild>
                            <a href={submission.paperTemplateUrl} download>
                                <Download />
                                Submission paper template
                            </a>
                        </Button>
                    )}
                    {statementLetterTemplateUrl && (
                        <Button variant="outline" size="sm" asChild>
                            <a href={statementLetterTemplateUrl} download>
                                <Download />
                                Statement letter template
                            </a>
                        </Button>
                    )}
                </div>
            )}

            <LogoUploadField
                label="Submission paper"
                currentUrl={isRevision ? submission.paperUrl : null}
                currentName={submission.paperOriginalName}
                file={form.data.paper}
                onChange={(file) => form.setData('paper', file)}
                invalid={Boolean(form.errors.paper)}
                accept={acceptList(requirements.paperExtensions)}
                hint={`${paperFormats} · max ${requirements.maxSizeMb} MB${keepCurrentHint}`}
            />

            {showStatementLetter && (
                <LogoUploadField
                    label="Statement letter (with Rp10,000 duty stamp)"
                    currentUrl={isRevision ? submission.statementUrl : null}
                    currentName={submission.statementOriginalName}
                    file={form.data.statement_letter}
                    onChange={(file) => form.setData('statement_letter', file)}
                    invalid={Boolean(form.errors.statement_letter)}
                    accept={acceptList(requirements.statementLetterExtensions)}
                    hint={`${statementFormats} · max ${requirements.maxSizeMb} MB${keepCurrentHint}`}
                />
            )}

            <p className="text-xs text-muted-foreground">
                {isRevision
                    ? 'Once resubmitted, your files are locked again while the committee reviews them.'
                    : 'Once submitted, your files are locked and can no longer be changed.'}
            </p>

            <div className="grid gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {isRevision ? 'Submit revision' : 'Submit paper'}
                </Button>
                {form.progress && (
                    <p className="text-center text-xs text-muted-foreground">
                        Uploading… {form.progress.percentage}%
                    </p>
                )}
            </div>
        </form>
    );
}

export default function ShowSubmission({
    submission,
    requirements,
    paperDeadline,
    isClosed,
    statementLetterTemplateUrl,
    contactEmail,
}: Props) {
    const formProps = {
        submission,
        requirements,
        paperDeadline,
        statementLetterTemplateUrl,
    };

    if (submission.isRevisionOpen) {
        return (
            <>
                <Head title="Revise your submission" />
                <PaperForm mode="revision" {...formProps} />
            </>
        );
    }

    if (submission.paperUploadedAt) {
        return (
            <>
                <Head title="Paper submitted" />
                <SubmittedState
                    submission={submission}
                    contactEmail={contactEmail}
                />
            </>
        );
    }

    if (isClosed) {
        return (
            <>
                <Head title="Submission closed" />
                <ClosedState
                    submission={submission}
                    contactEmail={contactEmail}
                />
            </>
        );
    }

    return (
        <>
            <Head title="Submit your paper" />
            <PaperForm mode="initial" {...formProps} />
        </>
    );
}

ShowSubmission.layout = {
    wide: true,
    title: 'Your ICS Award submission',
    description:
        'Upload your paper before the deadline to complete your entry.',
};
