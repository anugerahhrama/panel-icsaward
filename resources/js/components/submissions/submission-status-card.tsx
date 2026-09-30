import { Link } from '@inertiajs/react';
import {
    Check,
    CircleAlert,
    CircleCheck,
    CircleX,
    Clock,
    Download,
    MapPin,
    Trophy,
    Upload,
} from 'lucide-react';
import { LockedFile } from '@/components/submissions/locked-file';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTimeLeft } from '@/hooks/use-time-left';
import { formatDateTimeWib } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import { show } from '@/routes/submissions';

export type SubmissionStatus =
    | 'registered'
    | 'under_review'
    | 'needs_revision'
    | 'qualified'
    | 'disqualified'
    | 'finalist';

export type DashboardSubmission = {
    uuid: string;
    initiativeTitle: string;
    category: string;
    paperTemplateUrl: string | null;
    status: SubmissionStatus;
    revisionNote: string | null;
    revisionDeadline: string | null;
    isRevisionOpen: boolean;
    disqualifiedReason: string | null;
    paperUploadedAt: string | null;
    paperOriginalName: string | null;
    paperUrl: string | null;
    statementOriginalName: string | null;
    statementUrl: string | null;
    pitching: PitchingSchedule | null;
};

export type PitchingSchedule = {
    scheduledAt: string;
    startsAt: string | null;
    location: string | null;
    meetingLink: string | null;
};

export type PaperDeadline = {
    paperDeadline: string | null;
    isClosed: boolean;
    statementLetterTemplateUrl: string | null;
    contactEmail: string | null;
};

export const STATUS_LABELS: Record<SubmissionStatus, string> = {
    registered: 'Awaiting paper',
    under_review: 'Under review',
    needs_revision: 'Needs revision',
    qualified: 'Qualified',
    disqualified: 'Disqualified',
    finalist: 'Finalist',
};

const STATUS_STEP: Record<SubmissionStatus, number> = {
    registered: 1,
    under_review: 2,
    needs_revision: 2,
    qualified: 3,
    disqualified: 2,
    finalist: 4,
};

type StepTone = 'default' | 'warning' | 'danger';

function steps(status: SubmissionStatus): { title: string; tone: StepTone }[] {
    const selection: { title: string; tone: StepTone } =
        status === 'needs_revision'
            ? { title: 'Needs revision', tone: 'warning' }
            : status === 'disqualified'
              ? { title: 'Disqualified', tone: 'danger' }
              : { title: 'Qualified', tone: 'default' };

    return [
        { title: 'Registered', tone: 'default' },
        { title: 'Paper submitted', tone: 'default' },
        selection,
        { title: 'Finalist', tone: 'default' },
    ];
}

/**
 * Registered → Paper submitted → Qualified | Needs revision | Disqualified → Finalist.
 */
function StatusStepper({ status }: { status: SubmissionStatus }) {
    const reached = STATUS_STEP[status];

    return (
        <ol className="grid gap-3 sm:grid-cols-4 sm:gap-2">
            {steps(status).map((step, index) => {
                const isDone = index < reached;
                const isCurrent = index === reached - 1;
                const hasIssue = isCurrent && step.tone !== 'default';

                return (
                    <li
                        key={step.title}
                        className="flex items-center gap-2 sm:flex-col sm:items-start"
                    >
                        <div className="flex items-center gap-2 sm:w-full">
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                                    isDone
                                        ? 'border-brand bg-brand text-white'
                                        : 'text-muted-foreground',
                                    hasIssue &&
                                        step.tone === 'warning' &&
                                        'border-amber-500 bg-amber-500',
                                    hasIssue &&
                                        step.tone === 'danger' &&
                                        'border-destructive bg-destructive',
                                )}
                            >
                                {isDone && !hasIssue ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span
                                aria-hidden
                                className={cn(
                                    'hidden h-px flex-1 sm:block',
                                    index < reached - 1
                                        ? 'bg-brand'
                                        : 'bg-border',
                                    index === 3 && 'sm:hidden',
                                )}
                            />
                        </div>
                        <p
                            className={cn(
                                'text-sm',
                                isDone
                                    ? 'font-medium'
                                    : 'text-muted-foreground',
                                hasIssue &&
                                    step.tone === 'warning' &&
                                    'text-amber-700 dark:text-amber-400',
                                hasIssue &&
                                    step.tone === 'danger' &&
                                    'text-destructive',
                            )}
                        >
                            {step.title}
                        </p>
                    </li>
                );
            })}
        </ol>
    );
}

function UploadAction({
    submission,
    deadline,
}: {
    submission: DashboardSubmission;
    deadline: PaperDeadline;
}) {
    const timeLeft = useTimeLeft(deadline.paperDeadline);

    return (
        <div className="grid gap-4 rounded-lg border bg-muted/40 p-4">
            <div className="grid gap-1">
                <p className="text-sm font-medium">
                    Next step: submit your paper
                </p>
                {deadline.paperDeadline && (
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-muted-foreground">
                            Deadline:{' '}
                            <span className="font-medium text-foreground">
                                {formatDateTimeWib(deadline.paperDeadline)}
                            </span>
                        </span>
                        {timeLeft && (
                            <Badge variant="secondary">{timeLeft}</Badge>
                        )}
                    </div>
                )}
            </div>
            <div className="flex flex-wrap gap-2">
                <Button asChild>
                    <Link href={show(submission.uuid)}>
                        <Upload />
                        Upload paper
                    </Link>
                </Button>
                {submission.paperTemplateUrl && (
                    <Button variant="outline" asChild>
                        <a href={submission.paperTemplateUrl} download>
                            <Download />
                            Paper template
                        </a>
                    </Button>
                )}
                {deadline.statementLetterTemplateUrl && (
                    <Button variant="outline" asChild>
                        <a href={deadline.statementLetterTemplateUrl} download>
                            <Download />
                            Statement letter template
                        </a>
                    </Button>
                )}
            </div>
        </div>
    );
}

function ClosedAction({ contactEmail }: { contactEmail: string | null }) {
    return (
        <div className="flex gap-3 rounded-lg border bg-muted/40 p-4 text-sm">
            <Clock className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <p className="text-muted-foreground">
                <span className="font-medium text-foreground">
                    Submission closed.
                </span>{' '}
                The paper deadline has passed, so uploads are no longer
                accepted.
                {contactEmail && (
                    <>
                        {' '}
                        Questions? Contact{' '}
                        <a
                            href={`mailto:${contactEmail}`}
                            className="font-medium text-brand underline underline-offset-4"
                        >
                            {contactEmail}
                        </a>
                        .
                    </>
                )}
            </p>
        </div>
    );
}

function ContactLine({ contactEmail }: { contactEmail: string | null }) {
    if (!contactEmail) {
        return null;
    }

    return (
        <p className="text-muted-foreground">
            Questions? Contact{' '}
            <a
                href={`mailto:${contactEmail}`}
                className="font-medium text-brand underline underline-offset-4"
            >
                {contactEmail}
            </a>
            .
        </p>
    );
}

function RevisionPanel({
    submission,
    contactEmail,
}: {
    submission: DashboardSubmission;
    contactEmail: string | null;
}) {
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
                {submission.isRevisionOpen ? (
                    <div>
                        <Button asChild>
                            <Link href={show(submission.uuid)}>
                                <Upload />
                                Upload revised files
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <p className="text-muted-foreground">
                        The revision deadline has passed, so uploads are no
                        longer accepted.
                    </p>
                )}
                <ContactLine contactEmail={contactEmail} />
            </div>
        </div>
    );
}

function DisqualifiedPanel({
    submission,
    contactEmail,
}: {
    submission: DashboardSubmission;
    contactEmail: string | null;
}) {
    return (
        <div className="flex gap-3 rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm">
            <CircleX className="mt-0.5 size-4 shrink-0 text-destructive" />
            <div className="grid gap-3">
                <p className="font-medium">
                    Your submission does not meet the administrative
                    requirements.
                </p>
                {submission.disqualifiedReason && (
                    <div className="grid gap-1">
                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Reason
                        </p>
                        <p className="whitespace-pre-line">
                            {submission.disqualifiedReason}
                        </p>
                    </div>
                )}
                <ContactLine contactEmail={contactEmail} />
            </div>
        </div>
    );
}

function QualifiedPanel() {
    return (
        <div className="flex gap-3 rounded-lg border border-brand/40 bg-brand/5 p-4 text-sm">
            <CircleCheck className="mt-0.5 size-4 shrink-0 text-brand" />
            <p className="text-muted-foreground">
                <span className="font-medium text-foreground">
                    Your submission is qualified.
                </span>{' '}
                It passed the administrative check and moves on to the desk
                evaluation by the Board of Judges.
            </p>
        </div>
    );
}

function FinalistPanel({ pitching }: { pitching: PitchingSchedule | null }) {
    return (
        <div className="flex gap-3 rounded-lg border border-brand/40 bg-brand/5 p-4 text-sm">
            <Trophy className="mt-0.5 size-4 shrink-0 text-brand" />
            <div className="min-w-0 space-y-3">
                <p className="text-muted-foreground">
                    <span className="font-medium text-foreground">
                        Congratulations, you are a Top 5 finalist.
                    </span>{' '}
                    {pitching
                        ? 'You will pitch your initiative to the Board of Judges at the schedule below.'
                        : 'You will pitch your initiative to the Board of Judges. Your pitching schedule will appear on this dashboard, and the committee will contact you by email.'}
                </p>

                {pitching && (
                    <dl className="grid gap-2 rounded-md border bg-background p-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
                        <dt className="text-muted-foreground">Your pitch</dt>
                        <dd className="font-medium">
                            {pitching.startsAt
                                ? formatDateTimeWib(pitching.startsAt)
                                : 'Time to be announced'}
                        </dd>
                        <dt className="text-muted-foreground">
                            Session starts
                        </dt>
                        <dd>{formatDateTimeWib(pitching.scheduledAt)}</dd>
                        {pitching.location && (
                            <>
                                <dt className="text-muted-foreground">
                                    Location
                                </dt>
                                <dd className="flex items-start gap-1.5">
                                    <MapPin className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                    {pitching.location}
                                </dd>
                            </>
                        )}
                        {pitching.meetingLink && (
                            <>
                                <dt className="text-muted-foreground">
                                    Online
                                </dt>
                                <dd>
                                    <Button asChild size="sm" variant="outline">
                                        <a
                                            href={pitching.meetingLink}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            Join meeting
                                        </a>
                                    </Button>
                                </dd>
                            </>
                        )}
                    </dl>
                )}
            </div>
        </div>
    );
}

function VerificationPanel({
    submission,
    contactEmail,
}: {
    submission: DashboardSubmission;
    contactEmail: string | null;
}) {
    switch (submission.status) {
        case 'needs_revision':
            return (
                <RevisionPanel
                    submission={submission}
                    contactEmail={contactEmail}
                />
            );
        case 'disqualified':
            return (
                <DisqualifiedPanel
                    submission={submission}
                    contactEmail={contactEmail}
                />
            );
        case 'qualified':
            return <QualifiedPanel />;
        case 'finalist':
            return <FinalistPanel pitching={submission.pitching} />;
        default:
            return null;
    }
}

function SubmittedFiles({ submission }: { submission: DashboardSubmission }) {
    const uploadedAt = submission.paperUploadedAt
        ? `Submitted ${formatDateTimeWib(submission.paperUploadedAt)}`
        : undefined;

    return (
        <div className="grid gap-2">
            {submission.paperOriginalName && (
                <LockedFile
                    label="Submission paper"
                    name={submission.paperOriginalName}
                    href={submission.paperUrl}
                    detail={uploadedAt}
                />
            )}
            {submission.statementOriginalName && (
                <LockedFile
                    label="Statement letter"
                    name={submission.statementOriginalName}
                    href={submission.statementUrl}
                    detail={uploadedAt}
                />
            )}
            {submission.status === 'under_review' && (
                <p className="text-xs text-muted-foreground">
                    Your files are locked while the committee reviews them.
                </p>
            )}
        </div>
    );
}

export function SubmissionStatusCard({
    submission,
    deadline,
}: {
    submission: DashboardSubmission;
    deadline: PaperDeadline;
}) {
    const hasPaper = submission.paperUploadedAt !== null;

    return (
        <Card>
            <CardHeader className="gap-2">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <CardDescription className="text-xs font-semibold tracking-wide text-brand uppercase">
                        {submission.category}
                    </CardDescription>
                    <Badge
                        variant={
                            submission.status === 'disqualified'
                                ? 'destructive'
                                : 'secondary'
                        }
                    >
                        {STATUS_LABELS[submission.status]}
                    </Badge>
                </div>
                <CardTitle className="text-lg leading-snug">
                    {submission.initiativeTitle}
                </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-6">
                <StatusStepper status={submission.status} />

                {hasPaper && (
                    <VerificationPanel
                        submission={submission}
                        contactEmail={deadline.contactEmail}
                    />
                )}

                {hasPaper ? (
                    <SubmittedFiles submission={submission} />
                ) : deadline.isClosed ? (
                    <ClosedAction contactEmail={deadline.contactEmail} />
                ) : (
                    <UploadAction submission={submission} deadline={deadline} />
                )}
            </CardContent>
        </Card>
    );
}
