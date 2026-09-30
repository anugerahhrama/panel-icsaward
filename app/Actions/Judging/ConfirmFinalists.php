<?php

namespace App\Actions\Judging;

use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmFinalists
{
    public const int MAX_FINALISTS = 5;

    /**
     * Confirm the committee's finalists for a category and freeze its Stage 1 results, in one transaction.
     *
     * The selection only exists on the client until this runs, so a submission never becomes `finalist` while the
     * scores can still be recalculated (the recalculation only scores `qualified` submissions). The category is claimed
     * with a conditional update, so two admins confirming at once cannot both win. Holding the recalculation lock keeps
     * a running recalculation from rewriting the ranks underneath.
     *
     * @param  list<int>  $submissionIds
     *
     * @throws ValidationException
     */
    public function handle(AwardCategory $category, array $submissionIds, User $confirmer): int
    {
        $lock = Cache::lock(CalculateStageOneScores::LOCK_KEY, 60);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'finalists' => 'Scores are being recalculated. Try again in a moment.',
            ]);
        }

        try {
            return DB::transaction(fn (): int => $this->confirm($category, $submissionIds, $confirmer));
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<int>  $submissionIds
     */
    private function confirm(AwardCategory $category, array $submissionIds, User $confirmer): int
    {
        if (JudgingStage::current(lock: true) === JudgingStage::DeskEvaluation) {
            throw ValidationException::withMessages([
                'finalists' => 'Desk evaluation is still open. Close it in Settings → Judging before confirming finalists.',
            ]);
        }

        if ($submissionIds === [] || count($submissionIds) > self::MAX_FINALISTS) {
            throw ValidationException::withMessages([
                'finalists' => 'Select between 1 and '.self::MAX_FINALISTS.' finalists.',
            ]);
        }

        $claimed = AwardCategory::query()
            ->whereKey($category->id)
            ->whereNull('finalists_confirmed_at')
            ->update([
                'finalists_confirmed_at' => now(),
                'finalists_confirmed_by' => $confirmer->id,
            ]);

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'finalists' => 'The finalists for this category have already been confirmed.',
            ]);
        }

        $eligible = Submission::query()
            ->whereKey($submissionIds)
            ->where('award_category_id', $category->id)
            ->where('status', SubmissionStatus::Qualified)
            ->whereNotNull('stage1_rank')
            ->lockForUpdate()
            ->count();

        if ($eligible !== count($submissionIds)) {
            throw ValidationException::withMessages([
                'finalists' => 'Only ranked, qualified submissions of this category can become finalists. Reload the page and try again.',
            ]);
        }

        return Submission::query()
            ->whereKey($submissionIds)
            ->update(['status' => SubmissionStatus::Finalist]);
    }
}
