import { Link, usePage } from '@inertiajs/react';
import { KeyVisual } from '@/components/key-visual/key-visual';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';
import ibcsdLogo from '../../../images/brand/logo/ibcsd.webp';
import icsaLogo from '../../../images/brand/logo/icsa-2026.webp';
import olahkarsaLogo from '../../../images/brand/logo/olahkarsa-group.webp';

/**
 * Format a `YYYY-MM-DD` setting value in UTC so server and client render the same text.
 */
function formatDate(value: string, withYear: boolean): string | null {
    const date = new Date(`${value}T00:00:00Z`);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const day = date.getUTCDate();
    const month = new Intl.DateTimeFormat('en-US', {
        month: 'short',
        timeZone: 'UTC',
    }).format(date);

    return withYear
        ? `${day} ${month} ${date.getUTCFullYear()}`
        : `${day} ${month}`;
}

function RegistrationPeriod() {
    const { opensAt, closesAt } = usePage().props.registrationPeriod;
    const opens = opensAt ? formatDate(opensAt, false) : null;
    const closes = closesAt ? formatDate(closesAt, true) : null;

    if (!opens || !closes) {
        return null;
    }

    return (
        <p className="rounded-full bg-brand/10 px-3 py-1 text-xs font-semibold text-brand">
            Open Submission · {opens} – {closes}
        </p>
    );
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
    wide = false,
    hideRegistrationPeriod = false,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative isolate flex min-h-svh flex-col items-center justify-center gap-6 p-6 [--color-primary-foreground:var(--color-white)] [--color-primary:var(--color-brand)] [--color-ring:var(--color-brand)] md:p-10">
            <KeyVisual
                composition="corners"
                scrim="center"
                animated
                className="fixed"
            />
            <div
                className={cn(
                    'w-full rounded-2xl bg-background p-6 shadow-xl shadow-brand-dark/20 sm:p-8',
                    wide ? 'max-w-lg' : 'max-w-sm',
                )}
            >
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-3 text-center">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <img
                                src={icsaLogo}
                                alt={name}
                                className="h-14 w-auto sm:h-16"
                            />
                        </Link>
                        {!hideRegistrationPeriod && <RegistrationPeriod />}
                        <div className="flex flex-col gap-1">
                            <h1 className="font-display text-xl font-bold">
                                {title}
                            </h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
            <div className="flex items-center gap-4 rounded-2xl bg-background/90 px-4 py-2 shadow-lg shadow-brand-dark/20 backdrop-blur-sm sm:gap-6">
                <div className="flex flex-col items-start gap-1">
                    <span className="text-[10px] font-medium text-muted-foreground uppercase">
                        Organized by
                    </span>
                    <img
                        src={olahkarsaLogo}
                        alt="Olahkarsa Group"
                        className="h-6 w-auto sm:h-7"
                    />
                </div>
                <div className="h-8 w-px bg-border" aria-hidden />
                <div className="flex flex-col items-start gap-1">
                    <span className="text-[10px] font-medium text-muted-foreground uppercase">
                        Knowledge Partner
                    </span>
                    <img
                        src={ibcsdLogo}
                        alt="IBCSD"
                        className="h-6 w-auto sm:h-7"
                    />
                </div>
            </div>
        </div>
    );
}
