<?php

namespace App\Jobs;

use App\Enums\Announcement;
use App\Mail\CategoryAnnouncement;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Submission $submission, public Announcement $announcement) {}

    /**
     * Email the participant the committee's announcement, at most once per announcement.
     *
     * The announcement's notified column is claimed atomically before sending, so a duplicate or retried job that
     * finds it already set sends nothing. If sending fails the claim is released again so the queue retry can deliver it.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $column = $this->announcement->notifiedColumn();

        $claimed = Submission::query()
            ->whereKey($this->submission->id)
            ->whereNull($column)
            ->update([$column => now()]);

        if ($claimed === 0) {
            return;
        }

        $submission = $this->submission->fresh(['user', 'awardCategory.pitchingSession', 'pitchingSlot']);

        try {
            Mail::to($submission->user)->send(new CategoryAnnouncement($submission, $this->announcement));
        } catch (Throwable $exception) {
            Submission::query()->whereKey($submission->id)->update([$column => null]);

            throw $exception;
        }
    }
}
