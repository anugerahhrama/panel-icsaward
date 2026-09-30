<?php

namespace App\Actions\Judging;

use App\Enums\JudgingStage;
use App\Models\Judge;
use App\Models\JudgeScore;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveJudgeScores
{
    /**
     * Save the judge's scores for a submission in the active stage (desk evaluation or pitching), as a draft or as submitted.
     *
     * The stage, the assignment and the rubric are re-checked inside the transaction with the stage setting row
     * locked, so scores never land after the stage closes or after the judge was recused. Desk evaluation scores of a
     * category with confirmed finalists are frozen, even when desk evaluation is opened again, and pitching scores of a
     * category with confirmed awards likewise. Rows are upserted on
     * (submission, judge, criterion, stage), so double submits cannot duplicate them.
     *
     * @param  list<array{criterion_id: int, raw_score: int|null, notes: string|null}>  $scores
     *
     * @throws ValidationException
     */
    public function handle(Judge $judge, Submission $submission, array $scores, bool $submit): void
    {
        DB::transaction(function () use ($judge, $submission, $scores, $submit): void {
            $stage = JudgingStage::current(lock: true);

            if ($stage === JudgingStage::Closed) {
                throw ValidationException::withMessages([
                    'scores' => 'Judging is closed. Your scores can no longer be changed.',
                ]);
            }

            $submission = Submission::query()
                ->whereKey($submission->id)
                ->assignedToJudge($judge, $stage)
                ->with('awardCategory.assessmentTemplate.criteria:id,assessment_template_id')
                ->lockForUpdate()
                ->first();

            if ($submission === null) {
                throw ValidationException::withMessages([
                    'scores' => 'This submission is no longer assigned to you.',
                ]);
            }

            if ($submission->awardCategory->isScoringFrozen($stage)) {
                throw ValidationException::withMessages([
                    'scores' => $stage === JudgingStage::Pitching
                        ? 'Awards are confirmed for this category, so pitching scores are frozen.'
                        : 'Finalists are confirmed for this category, so desk evaluation scores are frozen.',
                ]);
            }

            $criterionIds = $submission->awardCategory->assessmentTemplate?->criteria->pluck('id')->all() ?? [];
            $sentIds = array_column($scores, 'criterion_id');

            if ($criterionIds === [] || array_diff($sentIds, $criterionIds) !== []) {
                throw ValidationException::withMessages([
                    'scores' => 'The scoring rubric has changed. Reload the page and try again.',
                ]);
            }

            $ownScores = JudgeScore::query()
                ->where('submission_id', $submission->id)
                ->where('judge_id', $judge->id)
                ->where('stage', $stage);

            if ($submit) {
                $scored = array_column(array_filter($scores, fn (array $row): bool => $row['raw_score'] !== null), 'criterion_id');

                if (array_diff($criterionIds, $scored) !== []) {
                    throw ValidationException::withMessages([
                        'scores' => 'Score every criterion before submitting.',
                    ]);
                }
            } elseif ((clone $ownScores)->whereNotNull('submitted_at')->exists()) {
                throw ValidationException::withMessages([
                    'scores' => 'These scores are already submitted. Use "Update scores" to change them.',
                ]);
            }

            $submittedAt = $submit ? now() : null;

            JudgeScore::query()->upsert(
                array_map(fn (array $row): array => [
                    'submission_id' => $submission->id,
                    'judge_id' => $judge->id,
                    'scoring_criterion_id' => $row['criterion_id'],
                    'stage' => $stage->value,
                    'raw_score' => $row['raw_score'],
                    'notes' => $row['notes'],
                    'submitted_at' => $submittedAt,
                ], $scores),
                ['submission_id', 'judge_id', 'scoring_criterion_id', 'stage'],
                ['raw_score', 'notes', 'submitted_at'],
            );
        });
    }
}
