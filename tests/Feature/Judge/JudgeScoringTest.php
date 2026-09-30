<?php

use App\Enums\JudgingStage;
use App\Models\AwardCategory;
use App\Models\JudgeScore;
use App\Models\ScoringCriterion;
use App\Models\Setting;
use App\Models\Submission;

beforeEach(function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);
});

/**
 * A score row per criterion of the category's template, with the same raw score.
 *
 * @return list<array{criterion_id: int, raw_score: int|null, notes: string|null}>
 */
function scoresFor(AwardCategory $category, ?int $rawScore = 80): array
{
    return $category->assessmentTemplate->criteria
        ->map(fn (ScoringCriterion $criterion): array => [
            'criterion_id' => $criterion->id,
            'raw_score' => $rawScore,
            'notes' => null,
        ])
        ->all();
}

test('judges can save a partial draft', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $scores = scoresFor($category, rawScore: null);
    $scores[0]['raw_score'] = 70;
    $scores[0]['notes'] = 'Strong governance.';

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'draft', 'scores' => $scores])
        ->assertRedirect(route('judge.submissions.show', $submission))
        ->assertSessionHasNoErrors();

    $saved = JudgeScore::query()->where('judge_id', $judge->id)->where('scoring_criterion_id', $scores[0]['criterion_id'])->sole();

    expect(JudgeScore::query()->count())->toBe(5)
        ->and($saved->raw_score)->toBe(70)
        ->and($saved->notes)->toBe('Strong governance.')
        ->and($saved->stage)->toBe(JudgingStage::DeskEvaluation)
        ->and(JudgeScore::query()->whereNotNull('submitted_at')->exists())->toBeFalse();
});

test('submitting requires a score for every criterion', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $scores = scoresFor($category);
    $scores[2]['raw_score'] = null;

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => $scores])
        ->assertSessionHasErrors(['scores.2.raw_score' => 'Enter a score before submitting.']);

    expect(JudgeScore::query()->exists())->toBeFalse();
});

test('submitting must cover the whole rubric', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => array_slice(scoresFor($category), 0, 4)])
        ->assertSessionHasErrors(['scores' => 'Score every criterion before submitting.']);

    expect(JudgeScore::query()->exists())->toBeFalse();
});

test('scores must be between 0 and 100', function (int $rawScore) {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category, $rawScore)])
        ->assertSessionHasErrors('scores.0.raw_score');
})->with(['below zero' => -1, 'above 100' => 101]);

test('submitted scores can still be updated while the stage is open, without duplicating rows', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category, 60)])
        ->assertSessionHasNoErrors();

    $this->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category, 85)])
        ->assertSessionHasNoErrors();

    expect(JudgeScore::query()->count())->toBe(5)
        ->and(JudgeScore::query()->pluck('raw_score')->unique()->all())->toBe([85])
        ->and(JudgeScore::query()->whereNull('submitted_at')->exists())->toBeFalse();
});

test('submitted scores cannot be turned back into a draft', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category, 60)]);

    $this->put(route('judge.submissions.scores.update', $submission), ['action' => 'draft', 'scores' => scoresFor($category, 10)])
        ->assertSessionHasErrors(['scores' => 'These scores are already submitted. Use "Update scores" to change them.']);

    expect(JudgeScore::query()->pluck('raw_score')->unique()->all())->toBe([60]);
});

test('scores are refused while judging is closed', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::Closed->value);
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'draft', 'scores' => scoresFor($category)])
        ->assertSessionHasErrors(['scores' => 'Judging is closed. Your scores can no longer be changed.']);

    expect(JudgeScore::query()->exists())->toBeFalse();
});

test('desk evaluation scores are frozen once the category has confirmed finalists', function () {
    $category = categoryWithRubric(finalistsConfirmed: true);
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->finalist()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category)])
        ->assertSessionHasErrors(['scores' => 'Finalists are confirmed for this category, so desk evaluation scores are frozen.']);

    expect(JudgeScore::query()->exists())->toBeFalse();
});

test('desk evaluation stays open for categories without confirmed finalists', function () {
    categoryWithRubric(finalistsConfirmed: true);
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category)])
        ->assertSessionHasNoErrors();

    expect(JudgeScore::query()->where('stage', JudgingStage::DeskEvaluation)->count())->toBe(5);
});

test('judges score confirmed finalists in pitching', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::Pitching->value);
    $category = categoryWithRubric(finalistsConfirmed: true);
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    submitScores($judge, $submission, 50);

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category, 90)])
        ->assertRedirect(route('judge.submissions.show', $submission))
        ->assertSessionHasNoErrors();

    expect(JudgeScore::query()->where('stage', JudgingStage::Pitching)->whereNotNull('submitted_at')->pluck('raw_score')->all())->toBe([90, 90, 90, 90, 90])
        ->and(JudgeScore::query()->where('stage', JudgingStage::DeskEvaluation)->pluck('raw_score')->unique()->all())->toBe([50]);
});

test('pitching scores are frozen once the category has confirmed awards', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::Pitching->value);
    $category = categoryWithRubric(finalistsConfirmed: true);
    $category->forceFill(['awards_confirmed_at' => now()])->save();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->finalist()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'submit', 'scores' => scoresFor($category)])
        ->assertSessionHasErrors(['scores' => 'Awards are confirmed for this category, so pitching scores are frozen.']);

    expect(JudgeScore::query()->exists())->toBeFalse();
});

test('submissions that are not finalists cannot be scored in pitching', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::Pitching->value);
    $category = categoryWithRubric(finalistsConfirmed: true);
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'draft', 'scores' => scoresFor($category)])
        ->assertForbidden();

    expect(JudgeScore::query()->exists())->toBeFalse();
});

test('a criterion from another template is refused', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $scores = scoresFor($category);
    $scores[0]['criterion_id'] = ScoringCriterion::factory()->create()->id;

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'draft', 'scores' => $scores])
        ->assertSessionHasErrors(['scores' => 'The scoring rubric has changed. Reload the page and try again.']);

    expect(JudgeScore::query()->exists())->toBeFalse();
});

test('judges cannot score submissions they are not assigned to, recused from, or that are not qualified', function (Closure $arrange) {
    $category = categoryWithRubric();
    [$judge, $submission] = $arrange($category);

    $this->actingAs($judge->user)
        ->put(route('judge.submissions.scores.update', $submission), ['action' => 'draft', 'scores' => scoresFor($category)])
        ->assertForbidden();

    expect(JudgeScore::query()->exists())->toBeFalse();
})->with([
    'unassigned' => fn (AwardCategory $category) => [
        judgeAssignedTo(categoryWithRubric()),
        Submission::factory()->qualified()->for($category, 'awardCategory')->create(),
    ],
    'recused' => fn (AwardCategory $category) => [
        judgeAssignedTo($category, recused: true),
        Submission::factory()->qualified()->for($category, 'awardCategory')->create(),
    ],
    'not qualified' => fn (AwardCategory $category) => [
        judgeAssignedTo($category),
        Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create(),
    ],
]);
