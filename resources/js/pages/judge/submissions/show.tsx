import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    type ScoringCriterion,
    ScoringForm,
} from '@/components/judge/scoring-form';
import { DocumentPreviewPanel } from '@/components/judge/document-preview-panel';
import { ScoringStatusBadge } from '@/components/judge/scoring-status-badge';
import type { SubmittedFile } from '@/components/submissions/file-preview';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTimeWib } from '@/lib/datetime';
import { dashboard } from '@/routes/judge';
import { index } from '@/routes/judge/submissions';

type Props = {
    submission: {
        uuid: string;
        initiative_title: string;
        initiative_description: string;
        company: string | null;
        category: string;
        template: string | null;
        paper_uploaded_at: string | null;
        paper: SubmittedFile | null;
        statement: SubmittedFile | null;
    };
    criteria: ScoringCriterion[];
    submittedAt: string | null;
    stage: { value: string; label: string };
    isScoringOpen: boolean;
    isFrozen: boolean;
};

export default function ScoreSubmission({
    submission,
    criteria,
    submittedAt,
    stage,
    isScoringOpen,
    isFrozen,
}: Props) {
    const hasScores = criteria.some(
        (criterion) => criterion.raw_score !== null,
    );

    return (
        <>
            <Head title={submission.initiative_title} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-clip rounded-xl p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title={submission.initiative_title}
                        description={[submission.company, submission.category]
                            .filter(Boolean)
                            .join(' · ')}
                    />
                    <div className="flex shrink-0 items-center gap-2">
                        <Badge variant="outline">{stage.label}</Badge>
                        <ScoringStatusBadge
                            status={
                                submittedAt
                                    ? 'submitted'
                                    : hasScores
                                      ? 'draft'
                                      : 'not_started'
                            }
                        />
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2 lg:items-start">
                    <DocumentPreviewPanel
                        paper={submission.paper}
                        statement={submission.statement}
                        paperUploadedAt={submission.paper_uploaded_at}
                        className="h-[70svh] min-w-0 lg:sticky lg:top-4 lg:order-last lg:h-[calc(100svh-2rem)]"
                    />

                    <div className="flex min-w-0 flex-col gap-4">
                        {!isScoringOpen && (
                            <Alert>
                                <AlertTitle>
                                    {isFrozen
                                        ? stage.value === 'pitching'
                                            ? 'Pitching is final'
                                            : 'Desk evaluation is final'
                                        : 'Scoring is closed'}
                                </AlertTitle>
                                <AlertDescription>
                                    {isFrozen
                                        ? stage.value === 'pitching'
                                            ? 'Awards are confirmed for this category, so your pitching scores are shown read-only.'
                                            : 'Finalists are confirmed for this category, so your desk evaluation scores are shown read-only.'
                                        : 'Your scores are shown read-only and cannot be changed right now.'}
                                </AlertDescription>
                            </Alert>
                        )}

                        {submittedAt && isScoringOpen && (
                            <Alert>
                                <AlertTitle>Scores submitted</AlertTitle>
                                <AlertDescription>
                                    Submitted on{' '}
                                    {formatDateTimeWib(submittedAt)}. You can
                                    still update them while{' '}
                                    {stage.value === 'pitching'
                                        ? 'pitching'
                                        : 'desk evaluation'}{' '}
                                    is open.
                                </AlertDescription>
                            </Alert>
                        )}

                        <Card>
                            <CardContent>
                                <p className="text-sm whitespace-pre-line">
                                    {submission.initiative_description}
                                </p>
                            </CardContent>
                        </Card>

                        {criteria.length === 0 ? (
                            <Alert variant="destructive">
                                <AlertTitle>No assessment template</AlertTitle>
                                <AlertDescription>
                                    This category has no scoring criteria yet.
                                    Please contact the committee.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <>
                                <Heading
                                    variant="small"
                                    title={submission.template ?? 'Assessment'}
                                    description="Score each criterion from 0 to 100. The weighted total is calculated as you type."
                                />
                                <ScoringForm
                                    submissionUuid={submission.uuid}
                                    criteria={criteria}
                                    isSubmitted={submittedAt !== null}
                                    isScoringOpen={isScoringOpen}
                                />
                            </>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

ScoreSubmission.layout = {
    breadcrumbs: [
        { title: 'Overview', href: dashboard() },
        { title: 'My Submissions', href: index() },
    ],
};
