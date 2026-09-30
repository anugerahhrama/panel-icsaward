<?php

namespace App\Actions\Judging;

use App\Enums\JudgingStage;
use App\Models\AwardCategory;
use App\Models\JudgeScore;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The scoring math shared by Stage 1 and Stage 2: weighted score per judge, normalization per judge and rank per category.
 */
class StageScoreCalculator
{
    public const string NORMALIZATION_ENABLED_KEY = 'normalization_enabled';

    public const string NORMALIZATION_MIN_SAMPLE_KEY = 'normalization_min_sample';

    public const int DEFAULT_MIN_SAMPLE = 3;

    /**
     * Scores are stored with four decimals, so ties are compared at that precision.
     */
    private const int PRECISION = 4;

    /**
     * Calculate the stage's results for every submission in `$submissions`, and store them in the `<prefix>_*` columns.
     *
     * Only the stage's submitted scores on those submissions count, and never those of a judge recused from the
     * submission's category. Each judge's weighted score is normalized with the judge's own mean and standard deviation
     * across every submission they scored in the stage (all categories), then scaled back to the global mean and
     * standard deviation so judges who fall back to their weighted raw score stay on the same 0–100 scale. Previous
     * results are cleared first, so submissions that are no longer eligible or no longer scored do not keep a stale rank.
     * Returns how many submissions got a rank.
     *
     * @param  Builder<Submission>  $submissions
     * @param  'stage1'|'stage2'  $prefix
     */
    public function calculateAndStore(JudgingStage $stage, Builder $submissions, string $prefix): int
    {
        $pairs = $this->weightedScores($stage, $submissions);
        $normalized = $this->normalize($pairs);

        $assigned = AwardCategory::query()
            ->withCount(['judges as active_judges_count' => fn ($judges) => $judges->where('category_judges.is_recused', false)])
            ->pluck('active_judges_count', 'id');

        $pairsBySubmission = [];

        foreach ($pairs as $index => $pair) {
            $pairsBySubmission[$pair['submission_id']][] = $index;
        }

        $results = [];

        foreach ((clone $submissions)->get(['id', 'award_category_id']) as $submission) {
            $indexes = $pairsBySubmission[$submission->id] ?? [];
            $raw = array_map(fn (int $index): float => $pairs[$index]['weighted'], $indexes);
            $scores = array_map(fn (int $index): float => $normalized[$index], $indexes);

            $results[$submission->id] = [
                'award_category_id' => $submission->award_category_id,
                'raw_score' => $raw === [] ? null : round(array_sum($raw) / count($raw), self::PRECISION),
                'score' => $scores === [] ? null : round(array_sum($scores) / count($scores), self::PRECISION),
                'judges_submitted' => count($raw),
                'judges_assigned' => (int) $assigned->get($submission->award_category_id, 0),
            ];
        }

        $ranks = $this->rank($results);
        $calculatedAt = now();

        DB::transaction(function () use ($results, $ranks, $calculatedAt, $prefix): void {
            Submission::query()->whereNotNull("{$prefix}_calculated_at")->toBase()->update([
                "{$prefix}_raw_score" => null,
                "{$prefix}_score" => null,
                "{$prefix}_rank" => null,
                "{$prefix}_judges_submitted" => null,
                "{$prefix}_judges_assigned" => null,
                "{$prefix}_calculated_at" => null,
            ]);

            foreach ($results as $submissionId => $result) {
                Submission::query()->whereKey($submissionId)->toBase()->update([
                    "{$prefix}_raw_score" => $result['raw_score'],
                    "{$prefix}_score" => $result['score'],
                    "{$prefix}_rank" => $ranks[$submissionId] ?? null,
                    "{$prefix}_judges_submitted" => $result['judges_submitted'],
                    "{$prefix}_judges_assigned" => $result['judges_assigned'],
                    "{$prefix}_calculated_at" => $calculatedAt,
                ]);
            }
        });

        return count($ranks);
    }

    /**
     * Each judge's weighted score (Σ raw × weight / 100) per submission.
     *
     * @param  Builder<Submission>  $submissions
     * @return list<array{judge_id: int, submission_id: int, weighted: float}>
     */
    private function weightedScores(JudgingStage $stage, Builder $submissions): array
    {
        $rows = JudgeScore::query()
            ->join('submissions', 'submissions.id', '=', 'judge_scores.submission_id')
            ->join('scoring_criteria', 'scoring_criteria.id', '=', 'judge_scores.scoring_criterion_id')
            ->join('category_judges', fn ($join) => $join
                ->on('category_judges.judge_id', '=', 'judge_scores.judge_id')
                ->on('category_judges.award_category_id', '=', 'submissions.award_category_id'))
            ->where('judge_scores.stage', $stage)
            ->whereNotNull('judge_scores.submitted_at')
            ->whereIn('judge_scores.submission_id', (clone $submissions)->select('submissions.id'))
            ->where('category_judges.is_recused', false)
            ->groupBy('judge_scores.judge_id', 'judge_scores.submission_id')
            ->selectRaw('judge_scores.judge_id, judge_scores.submission_id, sum(judge_scores.raw_score * scoring_criteria.weight) / 100 as weighted')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'judge_id' => (int) $row->judge_id,
                'submission_id' => (int) $row->submission_id,
                'weighted' => (float) $row->weighted,
            ]);

        return array_values($rows->all());
    }

    /**
     * The normalized score of every pair, keyed like `$pairs`.
     *
     * A pair keeps its weighted raw score when normalization is off, the judge scored fewer submissions than the
     * minimum sample, or either standard deviation is zero.
     *
     * @param  list<array{judge_id: int, submission_id: int, weighted: float}>  $pairs
     * @return list<float>
     */
    private function normalize(array $pairs): array
    {
        $weighted = array_column($pairs, 'weighted');

        if (Setting::get(self::NORMALIZATION_ENABLED_KEY, '1') !== '1' || $weighted === []) {
            return array_map(fn (array $pair): float => $pair['weighted'], $pairs);
        }

        $minSample = max(2, (int) Setting::get(self::NORMALIZATION_MIN_SAMPLE_KEY, (string) self::DEFAULT_MIN_SAMPLE));
        [$globalMean, $globalDeviation] = $this->statistics($weighted);

        $judges = [];

        foreach ($pairs as $pair) {
            $judges[$pair['judge_id']][] = $pair['weighted'];
        }

        $judges = array_map(fn (array $scores): array => [count($scores), ...$this->statistics($scores)], $judges);

        return array_map(function (array $pair) use ($judges, $minSample, $globalMean, $globalDeviation): float {
            [$sample, $mean, $deviation] = $judges[$pair['judge_id']];

            if ($sample < $minSample || $deviation < PHP_FLOAT_EPSILON || $globalDeviation < PHP_FLOAT_EPSILON) {
                return $pair['weighted'];
            }

            return $globalMean + ($pair['weighted'] - $mean) / $deviation * $globalDeviation;
        }, $pairs);
    }

    /**
     * Mean and population standard deviation.
     *
     * @param  non-empty-list<float>  $values
     * @return array{float, float}
     */
    private function statistics(array $values): array
    {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn (float $value): float => ($value - $mean) ** 2, $values)) / count($values);

        return [$mean, sqrt($variance)];
    }

    /**
     * Rank the scored submissions per category: highest score first, ties broken by the raw score, remaining ties
     * share a rank (1, 2, 2, 4). Submissions without a score are not ranked.
     *
     * @param  array<int, array{award_category_id: int, raw_score: float|null, score: float|null, judges_submitted: int, judges_assigned: int}>  $results
     * @return array<int, int> submission id => rank
     */
    private function rank(array $results): array
    {
        $byCategory = [];

        foreach ($results as $submissionId => $result) {
            if ($result['score'] !== null) {
                $byCategory[$result['award_category_id']][$submissionId] = [$result['score'], $result['raw_score']];
            }
        }

        return $this->rankPerCategory($byCategory);
    }

    /**
     * Rank each category's submissions by their sort key, highest first; equal keys share a rank (1, 2, 2, 4).
     *
     * @param  array<int, array<int, list<float|null>>>  $keysByCategory  category id => submission id => sort key
     * @return array<int, int> submission id => rank
     */
    public function rankPerCategory(array $keysByCategory): array
    {
        $ranks = [];

        foreach ($keysByCategory as $keys) {
            uasort($keys, fn (array $a, array $b): int => $b <=> $a);

            $position = 0;
            $previousKey = null;
            $previousRank = 0;

            foreach ($keys as $submissionId => $key) {
                $position++;
                $previousRank = $key === $previousKey ? $previousRank : $position;
                $previousKey = $key;
                $ranks[$submissionId] = $previousRank;
            }
        }

        return $ranks;
    }
}
