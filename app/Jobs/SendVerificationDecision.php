<?php

namespace App\Jobs;

use App\Mail\VerificationDecision;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendVerificationDecision implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Submission $submission) {}

    /**
     * Email the participant the committee's verification decision, at most once per decision.
     *
     * `notified_at` is claimed atomically before sending, so a duplicate or retried job that finds it already
     * set sends nothing. If sending fails the claim is released again so the queue retry can deliver it.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $claimed = Submission::query()
            ->whereKey($this->submission->id)
            ->whereNull('notified_at')
            ->update(['notified_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $submission = $this->submission->fresh(['user', 'awardCategory']);

        try {
            Mail::to($submission->user)->send(new VerificationDecision($submission));
        } catch (Throwable $exception) {
            Submission::query()->whereKey($submission->id)->update(['notified_at' => null]);

            throw $exception;
        }
    }
}
