<?php

namespace App\Actions\Judging;

use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalculateStageTwoScores
{
    /**
     * Only one recalculation may run at a time.
     */
    public const string LOCK_KEY = 'score-recap:stage-2';

    public const string STAGE_1_WEIGHT_KEY = 'stage_1_weight';

    public const string STAGE_2_WEIGHT_KEY = 'stage_2_weight';

    public const int DEFAULT_STAGE_WEIGHT = 50;

    /**
     * Scores are stored with four decimals.
     */
    private const int PRECISION = 4;

    public function __construct(private StageScoreCalculator $calculator) {}

    /**
     * The Stage 1 and Stage 2 weights (percent) of the final score.
     *
     * @return array{int, int}
     */
    public static function weights(): array
    {
        return [
            (int) Setting::get(self::STAGE_1_WEIGHT_KEY, (string) self::DEFAULT_STAGE_WEIGHT),
            (int) Setting::get(self::STAGE_2_WEIGHT_KEY, (string) self::DEFAULT_STAGE_WEIGHT),
        ];
    }

    /**
     * Recalculate and store `stage2_*` for every finalist of a category with confirmed finalists, then their final
     * score and rank, and return how many got a Stage 2 score.
     *
     * Only submitted pitching scores count; see `StageScoreCalculator` for the weighting, normalization and ranking,
     * which use the same normalization settings as Stage 1. Refused once any category has confirmed awards, since
     * those are based on the stored results.
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
            $confirmed = AwardCategory::query()->awardsConfirmed()->count();

            if ($confirmed > 0) {
                throw ValidationException::withMessages([
                    'recalculate' => "Awards are confirmed for {$confirmed} ".str('category')->plural($confirmed).'. Reopen them before recalculating.',
                ]);
            }

            $finalists = Submission::query()
                ->where('status', SubmissionStatus::Finalist)
                ->whereHas('awardCategory', fn ($category) => $category->finalistsConfirmed());

            $scored = $this->calculator->calculateAndStore(JudgingStage::Pitching, $finalists, 'stage2');

            $this->storeFinalScores((clone $finalists)->whereNotNull('stage1_score')->whereNotNull('stage2_score'));

            return $scored;
        } finally {
            $lock->release();
        }
    }

    /**
     * Final score = Stage 1 × w1 + Stage 2 × w2 (both already normalized), ranked per category. Equal final scores
     * share a rank; the committee breaks ties when confirming the awards.
     *
     * @param  Builder<Submission>  $submissions
     */
    private function storeFinalScores(Builder $submissions): void
    {
        [$stageOneWeight, $stageTwoWeight] = self::weights();

        $scores = [];
        $keysByCategory = [];

        foreach ($submissions->get(['id', 'award_category_id', 'stage1_score', 'stage2_score']) as $submission) {
            $score = round(($submission->stage1_score * $stageOneWeight + $submission->stage2_score * $stageTwoWeight) / 100, self::PRECISION);

            $scores[$submission->id] = $score;
            $keysByCategory[$submission->award_category_id][$submission->id] = [$score];
        }

        $ranks = $this->calculator->rankPerCategory($keysByCategory);
        $calculatedAt = now();

        DB::transaction(function () use ($scores, $ranks, $calculatedAt): void {
            Submission::query()->whereNotNull('final_calculated_at')->toBase()->update([
                'final_score' => null,
                'final_rank' => null,
                'final_calculated_at' => null,
            ]);

            foreach ($scores as $submissionId => $score) {
                Submission::query()->whereKey($submissionId)->toBase()->update([
                    'final_score' => $score,
                    'final_rank' => $ranks[$submissionId],
                    'final_calculated_at' => $calculatedAt,
                ]);
            }
        });
    }
}
