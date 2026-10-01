import { CopyPasswordButton } from '@/components/admin/copy-password-button';
import { Detail } from '@/components/admin/participants/detail';
import { SubmissionStatusBadge } from '@/components/admin/participants/submission-status-badge';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatDateTimeWib } from '@/lib/datetime';
import type { RegistrationRow } from '@/pages/admin/participants/registrations-columns';

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="grid gap-3">
            <h3 className="text-sm font-medium">{title}</h3>
            <dl className="grid gap-4 sm:grid-cols-2">{children}</dl>
        </section>
    );
}

function dateOrDash(value: string | null): string {
    return value ? formatDateTimeWib(value) : '—';
}

/**
 * Read-only details of one registration. The personal submission link only appears when the server sent it (superadmins).
 */
export function ParticipantDetailsSheet({
    registration,
    open,
    onOpenChange,
}: {
    registration: RegistrationRow | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Participant details</SheetTitle>
                    <SheetDescription>
                        {registration
                            ? `${registration.initiative_title} · ${registration.name}`
                            : ''}
                    </SheetDescription>
                </SheetHeader>
                {registration && (
                    <div className="grid gap-6 px-4 pb-4">
                        <Section title="Account">
                            <Detail label="Name" value={registration.name} />
                            <Detail
                                label="Email"
                                value={
                                    <span className="flex flex-wrap items-center gap-2 break-all">
                                        {registration.email}
                                        <Badge
                                            variant={
                                                registration.email_verified
                                                    ? 'secondary'
                                                    : 'outline'
                                            }
                                        >
                                            {registration.email_verified
                                                ? 'Verified'
                                                : 'Not verified'}
                                        </Badge>
                                    </span>
                                }
                            />
                            <Detail
                                label="Phone"
                                value={registration.phone ?? '—'}
                            />
                            <Detail
                                label="Position"
                                value={registration.position ?? '—'}
                            />
                            <Detail
                                label="Company"
                                value={registration.company_name ?? '—'}
                            />
                        </Section>

                        <Section title="Initiative">
                            <Detail
                                label="Category"
                                value={registration.category}
                            />
                            <Detail
                                label="Applicant type"
                                value={
                                    registration.applicant_type === 'individual'
                                        ? 'Individual'
                                        : 'Organization'
                                }
                            />
                            <div className="sm:col-span-2">
                                <Detail
                                    label="Title"
                                    value={registration.initiative_title}
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <Detail
                                    label="Description"
                                    value={registration.initiative_description}
                                />
                            </div>
                        </Section>

                        <Section title="Status">
                            <Detail
                                label="Status"
                                value={
                                    <SubmissionStatusBadge
                                        status={registration.status}
                                    />
                                }
                            />
                            <Detail
                                label="Registered"
                                value={dateOrDash(registration.registered_at)}
                            />
                            <Detail
                                label="Paper uploaded"
                                value={dateOrDash(
                                    registration.paper_uploaded_at,
                                )}
                            />
                            <Detail
                                label="Confirmation email queued"
                                value={dateOrDash(
                                    registration.confirmation_sent_at,
                                )}
                            />
                        </Section>

                        {registration.submission_url !== null && (
                            <section className="grid gap-2">
                                <h3 className="text-sm font-medium">
                                    Submission link
                                </h3>
                                <div className="flex gap-2">
                                    <Input
                                        readOnly
                                        value={registration.submission_url}
                                        aria-label="Submission link"
                                        onFocus={(event) =>
                                            event.target.select()
                                        }
                                    />
                                    <CopyPasswordButton
                                        value={registration.submission_url}
                                        label="link"
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Only the participant can open it while
                                    signed in to their own account. Opening it
                                    verifies their email.
                                </p>
                            </section>
                        )}
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
