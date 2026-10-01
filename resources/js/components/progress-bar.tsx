import { cn } from '@/lib/utils';

export function percentage(value: number, max: number): number {
    return max === 0 ? 0 : Math.round((value / max) * 100);
}

export function ProgressBar({
    value,
    max,
    className,
}: {
    value: number;
    max: number;
    className?: string;
}) {
    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={max}
            aria-valuenow={value}
            className={cn(
                'h-2 w-full overflow-hidden rounded-full bg-muted',
                className,
            )}
        >
            <div
                className="h-full rounded-full bg-primary transition-all"
                style={{ width: `${percentage(value, max)}%` }}
            />
        </div>
    );
}
