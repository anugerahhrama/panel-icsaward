import { useEffect, useState } from 'react';
import { SubmissionStatusBadge } from '@/components/admin/participants/submission-status-badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateTimeWib } from '@/lib/datetime';
import type { ActivityEvent } from './types';

const EVENT_LABELS: Record<ActivityEvent['type'], string> = {
    registered: 'Registered',
    paper_uploaded: 'Uploaded paper',
    reviewed: 'Verified',
};

const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

function formatRelative(at: string, now: number): string {
    const minutes = Math.round((new Date(at).getTime() - now) / 60_000);

    if (Math.abs(minutes) < 60) {
        return relative.format(minutes, 'minute');
    }

    const hours = Math.round(minutes / 60);

    if (Math.abs(hours) < 24) {
        return relative.format(hours, 'hour');
    }

    return relative.format(Math.round(hours / 24), 'day');
}

export function RecentActivity({ events }: { events: ActivityEvent[] }) {
    // Relative times depend on the clock, so they are computed after mount to avoid an SSR hydration mismatch.
    const [now, setNow] = useState<number | null>(null);

    useEffect(() => setNow(Date.now()), []);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Recent activity</CardTitle>
                <CardDescription>
                    Latest registrations, paper uploads and verification
                    decisions.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {events.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No activity yet.
                    </p>
                ) : (
                    <ul className="grid gap-4">
                        {events.map((event) => (
                            <li
                                key={event.id}
                                className="flex items-start justify-between gap-4 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="truncate">
                                        <span className="font-medium">
                                            {event.participant}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {EVENT_LABELS[
                                                event.type
                                            ].toLowerCase()}
                                            {event.actor &&
                                                ` by ${event.actor}`}
                                        </span>
                                    </p>
                                    <p className="truncate text-muted-foreground">
                                        {event.title}
                                        {event.category &&
                                            ` · ${event.category}`}
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    {event.type === 'reviewed' && (
                                        <SubmissionStatusBadge
                                            status={event.status}
                                        />
                                    )}
                                    <time
                                        dateTime={event.at}
                                        title={formatDateTimeWib(event.at)}
                                        className="text-xs text-muted-foreground"
                                    >
                                        {now === null
                                            ? formatDateTimeWib(event.at)
                                            : formatRelative(event.at, now)}
                                    </time>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
