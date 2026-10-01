import { APPLICANT_TYPE_LABELS } from '@/pages/admin/categories/columns';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { percentage } from '@/components/progress-bar';
import type { ApplicantTypeCount } from './types';

export function ApplicantTypeSplit({
    counts,
}: {
    counts: ApplicantTypeCount[];
}) {
    const total = counts.reduce((sum, count) => sum + count.registrations, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Applicant types</CardTitle>
                <CardDescription>
                    Registrations by the applicant type of their category.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {counts.map((count) => (
                    <div key={count.type} className="grid gap-1.5">
                        <div className="flex items-baseline justify-between gap-4 text-sm">
                            <span className="font-medium">
                                {APPLICANT_TYPE_LABELS[count.type]}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {count.registrations} registered ·{' '}
                                {count.papers} papers ·{' '}
                                {percentage(count.registrations, total)}%
                            </span>
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-primary"
                                style={{
                                    width: `${percentage(count.registrations, total)}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
