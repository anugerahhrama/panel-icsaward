<?php

namespace App\Actions\Submissions;

use App\Enums\SubmissionStatus;
use App\Jobs\SendVerificationDecision;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifySubmission
{
    /**
     * Record the committee's administrative verification decision and queue the participant notification.
     *
     * The update is conditional on the submission still being `under_review`, so two admins deciding at the
     * same time cannot both win: the second one affects no rows and is rejected. Details that do not belong
     * to the chosen decision are cleared.
     *
     * @param  array{revision_note: string|null, revision_deadline: CarbonImmutable|null, disqualified_reason: string|null}  $details
     *
     * @throws ValidationException
     */
    public function handle(Submission $submission, User $reviewer, SubmissionStatus $decision, array $details): void
    {
        DB::transaction(function () use ($submission, $reviewer, $decision, $details): void {
            $isRevision = $decision === SubmissionStatus::NeedsRevision;

            $updated = Submission::query()
                ->whereKey($submission->id)
                ->where('status', SubmissionStatus::UnderReview)
                ->update([
                    'status' => $decision,
                    'revision_note' => $isRevision ? $details['revision_note'] : null,
                    'revision_deadline' => $isRevision ? $details['revision_deadline'] : null,
                    'disqualified_reason' => $decision === SubmissionStatus::Disqualified ? $details['disqualified_reason'] : null,
                    'reviewed_at' => now(),
                    'reviewed_by' => $reviewer->id,
                    'notified_at' => null,
                ]);

            if ($updated === 0) {
                throw ValidationException::withMessages([
                    'decision' => 'This submission has already been reviewed.',
                ]);
            }

            SendVerificationDecision::dispatch($submission)->afterCommit();
        });
    }
}
