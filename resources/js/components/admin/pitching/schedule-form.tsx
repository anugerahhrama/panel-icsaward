import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { SaveButton } from '@/components/admin/save-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { destroy, update } from '@/routes/admin/pitching';

export type PitchingSessionDetails = {
    date: string;
    start_time: string;
    location: string | null;
    meeting_link: string | null;
};

export type PitchingFinalist = {
    id: number;
    initiative_title: string;
    company_name: string | null;
    stage1_rank: number | null;
    starts_at: string | null;
};

type ScheduleFormProps = {
    category: { id: number; name: string };
    session: PitchingSessionDetails | null;
    finalists: PitchingFinalist[];
    readOnly?: boolean;
};

/**
 * One pitching session per category; each finalist gets a start time (WIB) on the session date, or none yet.
 */
export function ScheduleForm({
    category,
    session,
    finalists,
    readOnly = false,
}: ScheduleFormProps) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        date: session?.date ?? '',
        start_time: session?.start_time ?? '',
        location: session?.location ?? '',
        meeting_link: session?.meeting_link ?? '',
        slots: finalists.map((finalist) => ({
            submission_id: finalist.id,
            starts_at: finalist.starts_at ?? '',
        })),
    });

    const fieldErrors = errors as Record<string, string | undefined>;

    const updateSlot = (index: number, startsAt: string) =>
        setData(
            'slots',
            data.slots.map((slot, position) =>
                position === index ? { ...slot, starts_at: startsAt } : slot,
            ),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        put(update(category.id).url, {
            preserveScroll: true,
            onError: (formErrors) =>
                toast.error(
                    formErrors.schedule ??
                        formErrors.slots ??
                        'Failed to save the schedule. Check the fields below.',
                ),
        });
    };

    const confirmDelete = () => {
        setDeleting(true);

        router.delete(destroy(category.id).url, {
            onError: () => toast.error('Failed to delete the schedule.'),
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <form onSubmit={submit}>
            <fieldset disabled={readOnly} className="space-y-6">
                <div className="grid max-w-2xl gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="date">Date</Label>
                        <Input
                            id="date"
                            type="date"
                            value={data.date}
                            required
                            onChange={(e) => setData('date', e.target.value)}
                        />
                        <InputError message={errors.date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="start_time">Start time (WIB)</Label>
                        <Input
                            id="start_time"
                            type="time"
                            value={data.start_time}
                            required
                            onChange={(e) =>
                                setData('start_time', e.target.value)
                            }
                        />
                        <InputError message={errors.start_time} />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="location">Location</Label>
                        <Input
                            id="location"
                            value={data.location}
                            maxLength={255}
                            placeholder="Venue and room"
                            onChange={(e) =>
                                setData('location', e.target.value)
                            }
                        />
                        <InputError message={errors.location} />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="meeting_link">Meeting link</Label>
                        <Input
                            id="meeting_link"
                            type="url"
                            value={data.meeting_link}
                            maxLength={500}
                            placeholder="https://"
                            onChange={(e) =>
                                setData('meeting_link', e.target.value)
                            }
                        />
                        <InputError message={errors.meeting_link} />
                        <p className="text-xs text-muted-foreground">
                            Fill in a location, a meeting link, or both.
                        </p>
                    </div>
                </div>

                <div className="max-w-2xl space-y-3">
                    <div>
                        <h3 className="text-base font-medium">
                            Finalist slots
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            Start time of each finalist on the session date.
                            Leave it empty if the time is not decided yet.
                        </p>
                    </div>

                    <ol className="divide-y rounded-md border">
                        {finalists.map((finalist, index) => (
                            <li
                                key={finalist.id}
                                className="grid gap-3 p-3 sm:grid-cols-[3rem_minmax(0,1fr)_9rem] sm:items-center"
                            >
                                <span className="text-sm font-medium text-muted-foreground tabular-nums">
                                    {finalist.stage1_rank !== null
                                        ? `#${finalist.stage1_rank}`
                                        : '—'}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {finalist.initiative_title}
                                    </p>
                                    {finalist.company_name && (
                                        <p className="truncate text-xs text-muted-foreground">
                                            {finalist.company_name}
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-1">
                                    <Label
                                        htmlFor={`slot-${finalist.id}`}
                                        className="sr-only"
                                    >
                                        Start time for{' '}
                                        {finalist.initiative_title}
                                    </Label>
                                    <Input
                                        id={`slot-${finalist.id}`}
                                        type="time"
                                        value={data.slots[index].starts_at}
                                        onChange={(e) =>
                                            updateSlot(index, e.target.value)
                                        }
                                    />
                                    <InputError
                                        message={
                                            fieldErrors[
                                                `slots.${index}.starts_at`
                                            ]
                                        }
                                    />
                                </div>
                            </li>
                        ))}
                    </ol>
                    <InputError message={fieldErrors.slots} />
                </div>

                {!readOnly && (
                    <div className="flex max-w-2xl items-center justify-between gap-4">
                        {session ? (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={() => setConfirmingDelete(true)}
                            >
                                Delete schedule
                            </Button>
                        ) : (
                            <span />
                        )}
                        <SaveButton processing={processing} />
                    </div>
                )}
            </fieldset>

            <Dialog open={confirmingDelete} onOpenChange={setConfirmingDelete}>
                <DialogContent>
                    <DialogTitle>Delete pitching schedule?</DialogTitle>
                    <DialogDescription>
                        This removes the session and every finalist slot of{' '}
                        <strong>{category.name}</strong>. Finalists will no
                        longer see a schedule on their dashboard.
                    </DialogDescription>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={deleting}
                            onClick={confirmDelete}
                        >
                            {deleting && <Spinner />}
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </form>
    );
}
