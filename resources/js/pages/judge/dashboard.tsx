import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { KeyVisual } from '@/components/key-visual/key-visual';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes/judge';
import submissions from '@/routes/judge/submissions';

type Progress = { total: number; submitted: number; draft: number };

type Props = {
    judgeName: string;
    stage: { value: string; label: string };
    scoringStage: { value: string; label: string };
    progress: Progress;
    categories: (Progress & { id: number; name: string })[];
};

function percentage({ total, submitted }: Progress): number {
    return total === 0 ? 0 : Math.round((submitted / total) * 100);
}

function ProgressBar({ progress }: { progress: Progress }) {
    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={progress.total}
            aria-valuenow={progress.submitted}
            className="h-2 w-full overflow-hidden rounded-full bg-muted"
        >
            <div
                className="h-full rounded-full bg-primary transition-all"
                style={{ width: `${percentage(progress)}%` }}
            />
        </div>
    );
}

export default function JudgeDashboard({
    judgeName,
    stage,
    scoringStage,
    progress,
    categories,
}: Props) {
    const isScoringOpen = stage.value !== 'closed';
    const isPitching = scoringStage.value === 'pitching';
    const remaining = progress.total - progress.submitted;

    return (
        <>
            <Head title="Overview" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <section className="relative isolate overflow-hidden rounded-xl p-6 text-white sm:p-8">
                    <KeyVisual composition="stage" scrim="left-bottom" />
                    <Badge
                        variant="outline"
                        className="border-white/40 text-white"
                    >
                        {stage.label}
                    </Badge>
                    <h1 className="mt-3 text-2xl font-semibold sm:text-3xl">
                        Welcome, {judgeName}
                    </h1>
                    <p className="mt-2 max-w-xl text-sm text-white/85">
                        {isScoringOpen
                            ? `${isPitching ? 'Pitching' : 'Desk evaluation'} is open. Score each ${isPitching ? 'finalist' : 'submission'} from 0 to 100 per criterion, save a draft any time, and submit when you are done.`
                            : 'Scoring is currently closed. You can still read the scores you have entered.'}
                    </p>
                    <div className="mt-6 flex flex-wrap items-end gap-x-10 gap-y-4">
                        <div>
                            <p className="text-4xl font-semibold tabular-nums">
                                {progress.submitted}
                                <span className="text-xl text-white/70">
                                    {' '}
                                    / {progress.total}
                                </span>
                            </p>
                            <p className="text-sm text-white/80">
                                submissions scored
                            </p>
                        </div>
                        <div>
                            <p className="text-4xl font-semibold tabular-nums">
                                {progress.draft}
                            </p>
                            <p className="text-sm text-white/80">in draft</p>
                        </div>
                        <Button variant="secondary" asChild>
                            <Link href={submissions.index()}>
                                {remaining > 0 && isScoringOpen
                                    ? 'Continue scoring'
                                    : 'My submissions'}
                                <ArrowRight />
                            </Link>
                        </Button>
                    </div>
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {isPitching
                                ? 'Pitching progress'
                                : 'Desk evaluation progress'}
                        </CardTitle>
                        <CardDescription>
                            {isPitching
                                ? 'Confirmed finalists in the categories assigned to you.'
                                : 'Qualified submissions in the categories assigned to you.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {categories.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                You have not been assigned to any category yet.
                                The committee will let you know once you are.
                            </p>
                        ) : (
                            <ul className="grid gap-4">
                                {categories.map((category) => (
                                    <li
                                        key={category.id}
                                        className="grid gap-2"
                                    >
                                        <div className="flex items-baseline justify-between gap-4 text-sm">
                                            <Link
                                                href={submissions.index({
                                                    query: {
                                                        category: category.id,
                                                    },
                                                })}
                                                className="font-medium hover:underline"
                                            >
                                                {category.name}
                                            </Link>
                                            <span className="shrink-0 text-muted-foreground tabular-nums">
                                                {category.submitted} /{' '}
                                                {category.total} submitted
                                                {category.draft > 0 &&
                                                    ` · ${category.draft} draft`}
                                            </span>
                                        </div>
                                        <ProgressBar progress={category} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

JudgeDashboard.layout = {
    breadcrumbs: [{ title: 'Overview', href: dashboard() }],
};
