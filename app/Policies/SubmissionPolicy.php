<?php

namespace App\Policies;

use App\Enums\JudgingStage;
use App\Enums\UserRole;
use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    /**
     * Only the participant who registered the submission may open its personal submission page.
     */
    public function view(User $user, Submission $submission): bool
    {
        return $submission->user_id === $user->id;
    }

    /**
     * The owner, the committee (admin/superadmin) and the judges who score it may download the submitted files.
     */
    public function downloadFiles(User $user, Submission $submission): bool
    {
        return $submission->user_id === $user->id
            || in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true)
            || $this->score($user, $submission);
    }

    /**
     * A judge may open and score a submission of the stage judges currently see (`JudgingStage::forJudges()`) in a
     * category they are assigned to and not recused from. Whether the stage is open is checked in `SaveJudgeScores`, so
     * judges can still read their scores afterwards.
     */
    public function score(User $user, Submission $submission): bool
    {
        if ($user->role !== UserRole::Judge || $user->judge === null) {
            return false;
        }

        return Submission::query()
            ->whereKey($submission->id)
            ->assignedToJudge($user->judge, JudgingStage::forJudges())
            ->exists();
    }

    /**
     * Only the owner may upload the paper; deadline and lock checks live in `SubmitPaper`.
     */
    public function update(User $user, Submission $submission): bool
    {
        return $submission->user_id === $user->id;
    }
}
