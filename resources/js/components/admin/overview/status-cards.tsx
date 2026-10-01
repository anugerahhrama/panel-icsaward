import { Link } from '@inertiajs/react';
import {
    STATUS_LABELS,
    type SubmissionStatus,
} from '@/components/submissions/submission-status-card';
import { Card, CardContent } from '@/components/ui/card';
import paperSubmissions from '@/routes/admin/participants/papers';
import registrations from '@/routes/admin/participants/registrations';
import type { StatusCounts } from './types';

const STATUS_ORDER: SubmissionStatus[] = [
    'registered',
    'under_review',
    'needs_revision',
    'qualified',
    'disqualified',
    'finalist',
];

export function StatusCards({ statusCounts }: { statusCounts: StatusCounts }) {
    return (
        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            {STATUS_ORDER.map((status) => {
                const query = { query: { status } };

                return (
                    <Link
                        key={status}
                        href={
                            status === 'registered'
                                ? registrations.index(query)
                                : paperSubmissions.index(query)
                        }
                        className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <Card className="h-full py-4 transition-colors hover:bg-muted/50">
                            <CardContent className="px-4">
                                <p className="text-sm text-muted-foreground">
                                    {STATUS_LABELS[status]}
                                </p>
                                <p className="mt-1 text-2xl font-semibold tabular-nums">
                                    {statusCounts.statuses[status]}
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                );
            })}
        </div>
    );
}
