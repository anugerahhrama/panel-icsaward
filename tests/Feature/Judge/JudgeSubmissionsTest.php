<?php

use App\Enums\JudgingStage;
use App\Models\JudgeScore;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('participants and admins cannot open the judge area', function (string $role) {
    $user = $role === 'participant' ? User::factory()->create() : User::factory()->admin()->create();

    $this->actingAs($user)
        ->get(route('judge.submissions.index'))
        ->assertForbidden();
})->with(['participant', 'admin']);

test('a judge account without a judge profile is refused', function () {
    $this->actingAs(User::factory()->judge()->create())
        ->get(route('judge.dashboard'))
        ->assertForbidden();
});

test('judges only see qualified submissions in categories they are assigned to and not recused from', function () {
    $category = categoryWithRubric();
    $recusedCategory = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $judge->categories()->attach($recusedCategory->id, ['is_recused' => true]);

    $assigned = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create();
    Submission::factory()->qualified()->for($recusedCategory, 'awardCategory')->create();
    Submission::factory()->qualified()->for(categoryWithRubric(), 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('judge/submissions/index')
            ->has('submissions.data', 1)
            ->where('submissions.data.0.uuid', $assigned->uuid)
            ->where('submissions.data.0.scoring_status', 'not_started')
            ->missing('submissions.data.0.email')
            ->has('categories', 1)
            ->where('categories.0.id', $category->id));
});

test('judges can filter by their own scoring status', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $otherJudge = judgeAssignedTo($category);
    $criterion = $category->assessmentTemplate->criteria->first();

    $submitted = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $draft = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $untouched = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    JudgeScore::factory()->submitted()->create(['submission_id' => $submitted->id, 'judge_id' => $judge->id, 'scoring_criterion_id' => $criterion->id]);
    JudgeScore::factory()->create(['submission_id' => $draft->id, 'judge_id' => $judge->id, 'scoring_criterion_id' => $criterion->id]);
    JudgeScore::factory()->submitted()->create(['submission_id' => $untouched->id, 'judge_id' => $otherJudge->id, 'scoring_criterion_id' => $criterion->id]);

    $expectations = ['submitted' => $submitted, 'draft' => $draft, 'not_started' => $untouched];

    foreach ($expectations as $status => $submission) {
        $this->actingAs($judge->user)
            ->get(route('judge.submissions.index', ['scoring_status' => $status]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.uuid', $submission->uuid)
                ->where('submissions.data.0.scoring_status', $status));
    }
});

test('the overview shows the active stage and the judge\'s own progress per category', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $criterion = $category->assessmentTemplate->criteria->first();

    $submitted = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    JudgeScore::factory()->submitted()->create(['submission_id' => $submitted->id, 'judge_id' => $judge->id, 'scoring_criterion_id' => $criterion->id]);

    $this->actingAs($judge->user)
        ->get(route('judge.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('judge/dashboard')
            ->where('stage.value', 'desk_evaluation')
            ->where('progress', ['total' => 2, 'submitted' => 1, 'draft' => 0])
            ->where('categories.0.id', $category->id));
});

test('the scoring page shows only the judge\'s own scores', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    $otherJudge = judgeAssignedTo($category);
    $criterion = $category->assessmentTemplate->criteria->first();
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    JudgeScore::factory()->create(['submission_id' => $submission->id, 'judge_id' => $otherJudge->id, 'scoring_criterion_id' => $criterion->id, 'raw_score' => 91]);

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.show', $submission))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('judge/submissions/show')
            ->has('criteria', 5)
            ->where('criteria.0.id', $criterion->id)
            ->where('criteria.0.raw_score', null)
            ->where('submittedAt', null)
            ->where('isScoringOpen', false));
});

test('judges cannot open a submission they are recused from', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category, recused: true);
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.show', $submission))
        ->assertForbidden();
});

test('in pitching judges only see the confirmed finalists, with their pitching progress', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::Pitching->value);
    $category = categoryWithRubric(finalistsConfirmed: true);
    $judge = judgeAssignedTo($category);

    $finalist = Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    submitScores($judge, $finalist, 70);

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stage.value', 'pitching')
            ->where('isScoringOpen', true)
            ->has('submissions.data', 1)
            ->where('submissions.data.0.uuid', $finalist->uuid)
            ->where('submissions.data.0.scoring_status', 'not_started'));

    $this->get(route('judge.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scoringStage.value', 'pitching')
            ->where('progress', ['total' => 1, 'submitted' => 0, 'draft' => 0]));
});

test('once finalists are confirmed, a closed judging stage shows judges the pitching view', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::Closed->value);
    $category = categoryWithRubric(finalistsConfirmed: true);
    $judge = judgeAssignedTo($category);
    $finalist = Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    submitScores($judge, $finalist, 60, JudgingStage::Pitching);

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.show', $finalist))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stage.value', 'pitching')
            ->where('criteria.0.raw_score', 60)
            ->where('isScoringOpen', false));
});

test('desk evaluation of a category with confirmed finalists is shown read-only', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);
    $category = categoryWithRubric(finalistsConfirmed: true);
    $judge = judgeAssignedTo($category);
    $finalist = Submission::factory()->finalist()->for($category, 'awardCategory')->create();

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.data.0.is_frozen', true));

    $this->get(route('judge.submissions.show', $finalist))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stage.value', 'desk_evaluation')
            ->where('isFrozen', true)
            ->where('isScoringOpen', false));
});
