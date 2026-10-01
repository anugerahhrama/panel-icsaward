import { usePage } from '@inertiajs/react';
import { KeyVisual } from '@/components/key-visual/key-visual';
import { Badge } from '@/components/ui/badge';
import { useTimeLeft } from '@/hooks/use-time-left';
import { formatDateTimeWib } from '@/lib/datetime';
import type { OverviewSummary, RegistrationState, StatusCounts } from './types';

const REGISTRATION_LABELS: Record<RegistrationState, string> = {
    open: 'Registration open',
    not_open: 'Registration not open yet',
    closed: 'Registration closed',
};

function Stat({ value, label }: { value: number; label: string }) {
    return (
        <div>
            <p className="text-4xl font-semibold tabular-nums">{value}</p>
            <p className="text-sm text-white/80">{label}</p>
        </div>
    );
}

export function OverviewBanner({
    summary,
    statusCounts,
}: {
    summary: OverviewSummary;
    statusCounts: StatusCounts;
}) {
    const { auth } = usePage().props;
    const { nextDeadline } = summary;
    const timeLeft = useTimeLeft(nextDeadline?.at ?? null);
    const { qualified, finalist } = statusCounts.statuses;

    return (
        <section className="relative isolate overflow-hidden rounded-xl p-6 text-white sm:p-8">
            <KeyVisual composition="stage" scrim="left-bottom" />
            <div className="flex flex-wrap gap-2">
                <Badge variant="outline" className="border-white/40 text-white">
                    {REGISTRATION_LABELS[summary.registration]}
                </Badge>
                <Badge variant="outline" className="border-white/40 text-white">
                    Judging: {summary.judgingStage.label}
                </Badge>
            </div>
            <h1 className="mt-3 text-2xl font-semibold sm:text-3xl">
                Welcome, {auth.user.name}
            </h1>
            <p className="mt-2 max-w-xl text-sm text-white/85">
                {nextDeadline
                    ? `${nextDeadline.label} on ${formatDateTimeWib(nextDeadline.at)}${timeLeft ? ` · ${timeLeft}` : ''}.`
                    : 'No upcoming registration or paper deadline.'}
            </p>
            <div className="mt-6 flex flex-wrap items-end gap-x-10 gap-y-4">
                <Stat
                    value={statusCounts.registrations}
                    label="registrations"
                />
                <Stat value={statusCounts.papers} label="papers submitted" />
                <Stat value={qualified + finalist} label="qualified" />
                <Stat value={finalist} label="finalists" />
            </div>
        </section>
    );
}
