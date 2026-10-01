import { Link } from '@inertiajs/react';
import { ProgressBar } from '@/components/progress-bar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import judges from '@/routes/admin/judges';
import type { JudgeProgress as JudgeProgressData } from './types';

export function JudgeProgress({ progress }: { progress: JudgeProgressData }) {
    const { total } = progress;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Judging progress</CardTitle>
                <CardDescription>
                    {progress.stage.label} · scores submitted per judge, most
                    work left first.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-2">
                    <div className="flex items-baseline justify-between text-sm">
                        <span className="font-medium">All judges</span>
                        <span className="text-muted-foreground tabular-nums">
                            {total.submitted} / {total.assigned} submitted
                            {total.draft > 0 && ` · ${total.draft} draft`}
                        </span>
                    </div>
                    <ProgressBar value={total.submitted} max={total.assigned} />
                </div>

                {progress.judges.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No judge is assigned to a category yet.{' '}
                        <Link
                            href={judges.index()}
                            className="underline underline-offset-4"
                        >
                            Assign judges
                        </Link>
                    </p>
                ) : (
                    <ul className="grid max-h-96 gap-3 overflow-y-auto pr-1">
                        {progress.judges.map((judge) => (
                            <li key={judge.id} className="grid gap-1.5">
                                <div className="flex items-baseline justify-between gap-4 text-sm">
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="truncate">
                                            {judge.name}
                                        </span>
                                        {!judge.has_account && (
                                            <Badge variant="outline">
                                                No account
                                            </Badge>
                                        )}
                                    </span>
                                    <span className="shrink-0 text-muted-foreground tabular-nums">
                                        {judge.submitted} / {judge.assigned}
                                        {judge.draft > 0 &&
                                            ` · ${judge.draft} draft`}
                                    </span>
                                </div>
                                <ProgressBar
                                    value={judge.submitted}
                                    max={judge.assigned}
                                    className="h-1.5"
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
