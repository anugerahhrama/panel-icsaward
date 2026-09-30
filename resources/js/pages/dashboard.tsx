import { Head, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import Heading from '@/components/heading';
import type {
    DashboardSubmission,
    PaperDeadline,
} from '@/components/submissions/submission-status-card';
import { SubmissionStatusCard } from '@/components/submissions/submission-status-card';
import type { TimelineStage } from '@/components/submissions/whats-next';
import { WhatsNext } from '@/components/submissions/whats-next';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

type Props = PaperDeadline & {
    submissions: DashboardSubmission[];
    timeline: TimelineStage[];
};

function ContactCommittee({ contactEmail }: { contactEmail: string | null }) {
    if (!contactEmail) {
        return null;
    }

    return (
        <div className="flex gap-3 border-t pt-4 text-sm">
            <Mail className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <p className="text-muted-foreground">
                Questions about your submission? Contact the committee at{' '}
                <a
                    href={`mailto:${contactEmail}`}
                    className="font-medium text-brand underline underline-offset-4"
                >
                    {contactEmail}
                </a>
                .
            </p>
        </div>
    );
}

export default function Dashboard({
    submissions,
    timeline,
    ...deadline
}: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={`Welcome, ${auth.user?.name ?? ''}`}
                    description="Track your ICS Award 2026 submission and what happens next."
                />

                <div className="grid items-start gap-6 lg:grid-cols-[1fr_340px]">
                    <div className="grid gap-4">
                        {submissions.length > 0 ? (
                            submissions.map((submission) => (
                                <SubmissionStatusCard
                                    key={submission.uuid}
                                    submission={submission}
                                    deadline={deadline}
                                />
                            ))
                        ) : (
                            <Card>
                                <CardContent className="grid gap-1 text-sm">
                                    <p className="font-medium">
                                        No submissions yet
                                    </p>
                                    <p className="text-muted-foreground">
                                        Your registered initiatives will appear
                                        here.
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="lg:sticky lg:top-4">
                        <CardContent className="grid gap-4">
                            <WhatsNext stages={timeline} />
                            <ContactCommittee
                                contactEmail={deadline.contactEmail}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
