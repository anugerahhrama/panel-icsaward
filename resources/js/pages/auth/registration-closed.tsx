import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDateTimeWib } from '@/lib/datetime';
import { login } from '@/routes';

type Props = {
    status: 'not_open' | 'closed';
    opensAt: string | null;
    closedAt: string | null;
    contactEmail: string | null;
};

export default function RegistrationClosed({
    status,
    opensAt,
    closedAt,
    contactEmail,
}: Props) {
    const isNotOpenYet = status === 'not_open';
    const title = isNotOpenYet
        ? 'Registration not open yet'
        : 'Registration closed';

    setLayoutProps({
        title,
        description: 'ICS Award 2026 initiative registration.',
        hideRegistrationPeriod: true,
    });

    let message = 'Registration for ICS Award 2026 is currently closed.';

    if (isNotOpenYet) {
        message = opensAt
            ? `Registration for ICS Award 2026 opens on ${formatDateTimeWib(opensAt)}.`
            : 'Registration for ICS Award 2026 is not open yet.';
    } else if (closedAt) {
        message = `Registration for ICS Award 2026 closed on ${formatDateTimeWib(closedAt)}.`;
    }

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-6">
                <div className="flex flex-col items-center gap-2 text-center">
                    <Clock className="size-10 text-muted-foreground" />
                    <p className="text-sm text-balance text-muted-foreground">
                        {message}
                        {contactEmail && (
                            <>
                                {' '}
                                If you have any questions, contact us at{' '}
                                <a
                                    href={`mailto:${contactEmail}`}
                                    className="font-medium text-brand underline underline-offset-4"
                                >
                                    {contactEmail}
                                </a>
                                .
                            </>
                        )}
                    </p>
                </div>

                <div className="grid gap-2">
                    <p className="text-center text-sm text-muted-foreground">
                        Already registered? Log in to submit your paper.
                    </p>
                    <Button asChild>
                        <Link href={login()}>Log in</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
