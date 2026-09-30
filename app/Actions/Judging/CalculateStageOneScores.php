<?php

namespace App\Actions\Judging;

use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\Submission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CalculateStageOneScores
{
    /**
     * Only one recalculation may run at a time.
     */
    public const string LOCK_KEY = 'score-recap:stage-1';

    public function __construct(private StageScoreCalculator $calculator) {}

    /**
     * Recalculate and store `stage1_*` for every qualified submission, and return how many got a score.
     *
     * Only submitted desk evaluation scores count; see `StageScoreCalculator` for the weighting, normalization and
     * ranking. Confirmed finalists freeze the results, so the recalculation is refused while any category has them.
     *
     * @throws ValidationException
     */
    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 60);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'recalculate' => 'Scores are already being recalculated. Try again in a moment.',
            ]);
        }

        try {
            $confirmed = AwardCategory::query()->finalistsConfirmed()->count();

            if ($confirmed > 0) {
                throw ValidationException::withMessages([
                    'recalculate' => "Finalists are confirmed for {$confirmed} ".str('category')->plural($confirmed).'. Reopen them before recalculating.',
                ]);
            }

            return $this->calculator->calculateAndStore(
                JudgingStage::DeskEvaluation,
                Submission::query()->where('status', SubmissionStatus::Qualified),
                'stage1',
            );
        } finally {
            $lock->release();
        }
    }
}
