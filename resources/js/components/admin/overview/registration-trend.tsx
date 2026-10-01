import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { TrendDay } from './types';

/**
 * Brand blue and green, validated as a categorical pair (CVD + contrast) against the light and dark card surfaces.
 */
const SERIES = [
    {
        key: 'registrations',
        label: 'Registrations',
        className: 'bg-[#0567cc] dark:bg-[#3b87dc]',
    },
    {
        key: 'papers',
        label: 'Papers',
        className: 'bg-[#00a64c] dark:bg-[#14a85a]',
    },
] as const;

const dayLabel = new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
});

function formatDay(date: string): string {
    return dayLabel.format(new Date(`${date}T00:00:00Z`));
}

/**
 * A rounded-up axis maximum so the gridlines land on whole numbers.
 */
function niceMax(value: number): number {
    if (value <= 4) {
        return 4;
    }

    const step = 10 ** Math.floor(Math.log10(value));

    return Math.ceil(value / step) * step;
}

export function RegistrationTrend({ days }: { days: TrendDay[] }) {
    const max = niceMax(
        Math.max(
            0,
            ...days.map((day) => Math.max(day.registrations, day.papers)),
        ),
    );
    const totals = SERIES.map((series) =>
        days.reduce((sum, day) => sum + day[series.key], 0),
    );
    const labelEvery = Math.ceil(days.length / 6);

    return (
        <Card>
            <CardHeader>
                <CardTitle>Daily registrations & papers</CardTitle>
                <CardDescription>
                    Last {days.length} days, grouped by day in WIB.
                </CardDescription>
                <ul className="flex flex-wrap gap-4 pt-2 text-sm">
                    {SERIES.map((series, index) => (
                        <li
                            key={series.key}
                            className="flex items-center gap-2"
                        >
                            <span
                                aria-hidden
                                className={`size-2.5 rounded-sm ${series.className}`}
                            />
                            <span>{series.label}</span>
                            <span className="text-muted-foreground tabular-nums">
                                {totals[index]}
                            </span>
                        </li>
                    ))}
                </ul>
            </CardHeader>
            <CardContent>
                <div className="flex gap-2">
                    <div className="flex h-48 flex-col justify-between text-right text-xs text-muted-foreground tabular-nums">
                        <span>{max}</span>
                        <span>{max / 2}</span>
                        <span>0</span>
                    </div>
                    <div className="relative h-48 flex-1">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0 flex flex-col justify-between"
                        >
                            <div className="border-t border-border/60" />
                            <div className="border-t border-border/60" />
                            <div className="border-t border-border" />
                        </div>
                        <div className="absolute inset-0 flex items-end">
                            {days.map((day) => (
                                <Tooltip key={day.date}>
                                    <TooltipTrigger asChild>
                                        <div
                                            tabIndex={0}
                                            aria-label={`${formatDay(day.date)}: ${day.registrations} registrations, ${day.papers} papers`}
                                            className="flex h-full flex-1 items-end justify-center gap-0.5 rounded-sm px-px hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none"
                                        >
                                            {SERIES.map((series) => (
                                                <div
                                                    key={series.key}
                                                    className={`w-full max-w-2 rounded-t-[3px] ${series.className}`}
                                                    style={{
                                                        height: `${(day[series.key] / max) * 100}%`,
                                                    }}
                                                />
                                            ))}
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="font-medium">
                                            {formatDay(day.date)}
                                        </p>
                                        <p>{day.registrations} registrations</p>
                                        <p>{day.papers} papers</p>
                                    </TooltipContent>
                                </Tooltip>
                            ))}
                        </div>
                    </div>
                </div>
                <div className="mt-2 ml-8 flex text-xs text-muted-foreground">
                    {days.map((day, index) => (
                        <span key={day.date} className="flex-1 text-center">
                            {index % labelEvery === 0
                                ? formatDay(day.date)
                                : ''}
                        </span>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
