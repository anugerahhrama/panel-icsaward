import { useEffect, useState } from 'react';

function formatTimeLeft(milliseconds: number): string {
    const hours = Math.floor(milliseconds / 3_600_000);

    if (hours >= 48) {
        return `${Math.floor(hours / 24)} days left`;
    }

    if (hours >= 1) {
        return `${hours} hours left`;
    }

    return 'Less than an hour left';
}

/**
 * Time-dependent text is computed after mount to avoid an SSR hydration mismatch.
 */
export function useTimeLeft(deadline: string | null): string | null {
    const [timeLeft, setTimeLeft] = useState<string | null>(null);

    useEffect(() => {
        if (!deadline) {
            return;
        }

        const update = () => {
            const remaining = new Date(deadline).getTime() - Date.now();
            setTimeLeft(remaining > 0 ? formatTimeLeft(remaining) : null);
        };

        update();
        const interval = window.setInterval(update, 60_000);

        return () => window.clearInterval(interval);
    }, [deadline]);

    return timeLeft;
}
