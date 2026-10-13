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
     * Record (or change) the committee's administrative verification decision and queue the participant notification.
     *
     * A submission under review gets its first decision; an earlier decision (qualified, needs revision,
     * disqualified) may be changed until judges have scored the submission. The update is conditional on the
     * status the request was made against, so a submission that moved on in the meantime (a revision upload,
     * a finalist confirmation) is rejected instead of overwritten. Details that do not belong to the chosen
     * decision are cleared.
     *
     * @param  array{revision_note: string|null, revision_deadline: CarbonImmutable|null, disqualified_reason: string|null}  $details
     *
     * @throws ValidationException
     */
    public function handle(Submission $submission, User $reviewer, SubmissionStatus $decision, array $details): void
    {
        if (! in_array($submission->status, self::decidableStatuses(), true)) {
            throw ValidationException::withMessages([
                'decision' => 'This submission can no longer be verified.',
            ]);
        }

        DB::transaction(function () use ($submission, $reviewer, $decision, $details): void {
            $isRevision = $decision === SubmissionStatus::NeedsRevision;

            $updated = Submission::query()
                ->whereKey($submission->id)
                ->where('status', $submission->status)
                ->whereDoesntHave('judgeScores')
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
                    'decision' => 'This submission changed in the meantime or has already been scored by judges.',
                ]);
            }

            SendVerificationDecision::dispatch($submission)->afterCommit();
        });
    }

    /**
     * The statuses a decision can be recorded on: the first review, or a change to an earlier decision.
     *
     * @return list<SubmissionStatus>
     */
    public static function decidableStatuses(): array
    {
        return [SubmissionStatus::UnderReview, ...SubmissionStatus::verificationDecisions()];
    }
}
