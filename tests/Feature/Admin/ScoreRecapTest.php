<?php

use App\Actions\Judging\CalculateStageOneScores;
use App\Actions\Judging\CalculateStageTwoScores;
use App\Actions\Judging\StageScoreCalculator;
use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use App\Exports\ScoreRecapExport;
use App\Models\AwardCategory;
use App\Models\JudgeScore;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;

function recalculate(): void
{
    test()->actingAs(User::factory()->admin()->create())
        ->post(route('admin.score-recap.recalculate'))
        ->assertInertiaFlash('toast.type', 'success');
}

function recalculateStageTwo(): void
{
    test()->actingAs(User::factory()->admin()->create())
        ->post(route('admin.score-recap.stage-2.recalculate'))
        ->assertInertiaFlash('toast.type', 'success');
}

/**
 * A finalist with stored Stage 1 and Stage 2 scores.
 */
function finalistWithScores(AwardCategory $category, float $stageOne, float $stageTwo): Submission
{
    $submission = Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    $submission->forceFill([
        'stage1_score' => $stageOne,
        'stage1_rank' => 1,
        'stage1_calculated_at' => now(),
    ])->save();
    submitScores(judgeAssignedTo($category), $submission, (int) $stageTwo, JudgingStage::Pitching);

    return $submission;
}

function withoutNormalization(): void
{
    Setting::put(StageScoreCalculator::NORMALIZATION_ENABLED_KEY, '0');
}

test('judges cannot view or recalculate the score recap', function () {
    $judge = User::factory()->judge()->create();

    $this->actingAs($judge)->get(route('admin.score-recap.index'))->assertForbidden();
    $this->actingAs($judge)->post(route('admin.score-recap.recalculate'))->assertForbidden();
    $this->actingAs($judge)->post(route('admin.score-recap.stage-2.recalculate'))->assertForbidden();
});

test('the weighted score is the sum of raw score times weight', function () {
    withoutNormalization();
    $category = categoryWithRubric();
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    // Weights 15/20/35/15/15: 15 + 10 + 28 + 0 + 9.
    submitScores(judgeAssignedTo($category), $submission, [100, 50, 80, 0, 60]);

    recalculate();

    expect($submission->fresh())
        ->stage1_raw_score->toBe(62.0)
        ->stage1_score->toBe(62.0)
        ->stage1_rank->toBe(1)
        ->stage1_judges_submitted->toBe(1)
        ->stage1_judges_assigned->toBe(1)
        ->stage1_calculated_at->not->toBeNull();
});

test('draft scores, recused judges and submissions that are not qualified are left out', function () {
    withoutNormalization();
    $category = categoryWithRubric();
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $underReview = Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create();

    $judge = judgeAssignedTo($category);
    submitScores($judge, $submission, 80);
    submitScores($judge, $underReview, 90);
    submitScores(judgeAssignedTo($category, recused: true), $submission, 20);
    JudgeScore::factory()->create([
        'submission_id' => $submission->id,
        'judge_id' => judgeAssignedTo($category)->id,
        'scoring_criterion_id' => $category->assessmentTemplate->criteria()->value('id'),
        'raw_score' => 0,
    ]);

    recalculate();

    expect($submission->fresh())
        ->stage1_score->toBe(80.0)
        ->stage1_judges_submitted->toBe(1)
        ->stage1_judges_assigned->toBe(2)
        ->and($underReview->fresh()->stage1_calculated_at)->toBeNull();
});

test('each judge is normalized against everything they scored and scaled back to the overall range', function () {
    Setting::put(StageScoreCalculator::NORMALIZATION_MIN_SAMPLE_KEY, '3');
    $category = categoryWithRubric();
    [$low, $middle, $high] = Submission::factory()->qualified()->for($category, 'awardCategory')->count(3)->create()->all();
    $strict = judgeAssignedTo($category);
    $generous = judgeAssignedTo($category);

    foreach ([[$low, 60, 80], [$middle, 70, 85], [$high, 80, 90]] as [$submission, $strictScore, $generousScore]) {
        submitScores($strict, $submission, $strictScore);
        submitScores($generous, $submission, $generousScore);
    }

    recalculate();

    // Both judges put the submissions 1.2247 standard deviations apart; the overall mean is 77.5 with deviation 9.8953.
    $spread = sqrt(1.5) * sqrt(587.5 / 6);

    expect($low->fresh())
        ->stage1_raw_score->toBe(70.0)
        ->stage1_score->toEqualWithDelta(77.5 - $spread, 0.0001)
        ->stage1_rank->toBe(3)
        ->and($middle->fresh())
        ->stage1_score->toEqualWithDelta(77.5, 0.0001)
        ->stage1_rank->toBe(2)
        ->and($high->fresh())
        ->stage1_score->toEqualWithDelta(77.5 + $spread, 0.0001)
        ->stage1_rank->toBe(1);
});

test('judges below the minimum sample or without any spread keep their weighted raw score', function () {
    Setting::put(StageScoreCalculator::NORMALIZATION_MIN_SAMPLE_KEY, '3');
    $category = categoryWithRubric();
    $submissions = Submission::factory()->qualified()->for($category, 'awardCategory')->count(3)->create();
    $flat = judgeAssignedTo($category);
    $spread = judgeAssignedTo($category);
    $newcomer = judgeAssignedTo($category);

    foreach ($submissions as $index => $submission) {
        submitScores($flat, $submission, 70);
        submitScores($spread, $submission, [80, 85, 90][$index]);
    }

    submitScores($newcomer, $submissions[1], 40);

    recalculate();

    // Overall mean of the seven weighted scores: (210 + 255 + 40) / 7. The spread judge's middle score has z = 0.
    expect($submissions[1]->fresh())
        ->stage1_score->toEqualWithDelta((70 + 505 / 7 + 40) / 3, 0.0001)
        ->stage1_judges_submitted->toBe(3);
});

test('turning normalization off ranks by the average weighted raw score', function () {
    withoutNormalization();
    $category = categoryWithRubric();
    $submission = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $other = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $first = judgeAssignedTo($category);
    $second = judgeAssignedTo($category);

    submitScores($first, $submission, 60);
    submitScores($second, $submission, 90);
    submitScores($first, $other, 70);
    submitScores($second, $other, 70);

    recalculate();

    expect($submission->fresh())
        ->stage1_score->toBe(75.0)
        ->stage1_rank->toBe(1)
        ->and($other->fresh()->stage1_rank)->toBe(2);
});

test('equal scores share a rank and unscored submissions stay unranked', function () {
    withoutNormalization();
    $category = categoryWithRubric();
    [$first, $second, $third, $unscored] = Submission::factory()->qualified()->for($category, 'awardCategory')->count(4)->create()->all();
    $judge = judgeAssignedTo($category);

    submitScores($judge, $first, 80);
    submitScores($judge, $second, 80);
    submitScores($judge, $third, 70);

    recalculate();

    expect([$first->fresh()->stage1_rank, $second->fresh()->stage1_rank, $third->fresh()->stage1_rank])->toBe([1, 1, 3])
        ->and($unscored->fresh())
        ->stage1_score->toBeNull()
        ->stage1_rank->toBeNull()
        ->stage1_judges_submitted->toBe(0)
        ->stage1_judges_assigned->toBe(1);
});

test('equal normalized scores are ranked by the raw score', function () {
    Setting::put(StageScoreCalculator::NORMALIZATION_MIN_SAMPLE_KEY, '2');
    $category = categoryWithRubric();
    [$a, $b, $x, $y] = Submission::factory()->qualified()->for($category, 'awardCategory')->count(4)->create()->all();
    $first = judgeAssignedTo($category);
    $second = judgeAssignedTo($category);

    // Both judges score one submission a deviation below and one above their own mean.
    submitScores($first, $a, 50);
    submitScores($first, $b, 70);
    submitScores($second, $x, 80);
    submitScores($second, $y, 100);

    recalculate();

    expect(collect([$y, $b, $x, $a])->map(fn (Submission $submission) => $submission->fresh()->stage1_rank)->all())
        ->toBe([1, 2, 3, 4])
        ->and($a->fresh()->stage1_score)->toBe($x->fresh()->stage1_score);
});

test('recalculating clears results that no longer apply', function () {
    $disqualified = Submission::factory()->create(['status' => SubmissionStatus::Disqualified]);
    $disqualified->forceFill(['stage1_score' => 90, 'stage1_rank' => 1, 'stage1_calculated_at' => now()])->save();

    recalculate();

    expect($disqualified->fresh())
        ->stage1_score->toBeNull()
        ->stage1_rank->toBeNull()
        ->stage1_calculated_at->toBeNull();
});

test('a recalculation that is already running is refused', function () {
    $submission = Submission::factory()->qualified()->create();
    $lock = Cache::lock(CalculateStageOneScores::LOCK_KEY, 60);
    $lock->get();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.score-recap.recalculate'))
        ->assertInertiaFlash('toast.type', 'error');

    $lock->release();

    expect($submission->fresh()->stage1_calculated_at)->toBeNull();
});

test('the score recap lists qualified submissions per category by rank', function () {
    withoutNormalization();
    $category = categoryWithRubric();
    [$runnerUp, $winner] = Submission::factory()->qualified()->for($category, 'awardCategory')->count(2)->create()->all();
    Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create();
    $judge = judgeAssignedTo($category);
    submitScores($judge, $runnerUp, 60);
    submitScores($judge, $winner, 90);

    recalculate();

    $this->get(route('admin.score-recap.index', ['category' => $category->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/score-recap/index')
            ->has('submissions.data', 2)
            ->where('submissions.data.0.id', $winner->id)
            ->where('submissions.data.0.rank', 1)
            ->where('submissions.data.0.score', 90)
            ->where('submissions.data.1.id', $runnerUp->id)
            ->where('filters.sort', 'rank')
            ->whereNot('calculatedAt', null)
            ->where('normalization.enabled', false));
});

test('the score recap export uses the table filters', function () {
    Excel::fake();
    $this->travelTo(now()->setTimezone('UTC')->setDateTime(2026, 10, 1, 3, 30));
    $category = categoryWithRubric();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    Submission::factory()->qualified()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.score-recap.export', ['category' => $category->id]))
        ->assertOk();

    Excel::assertDownloaded('score-recap-stage-1-20261001-1030.xlsx', function (ScoreRecapExport $export) use ($category) {
        return $export->query()->count() === 1
            && $export->map($export->query()->sole())[0] === $category->name;
    });
});

test('stage 2 ranks the confirmed finalists from submitted pitching scores only', function () {
    withoutNormalization();
    $category = categoryWithRubric(finalistsConfirmed: true);
    [$runnerUp, $winner] = Submission::factory()->finalist()->for($category, 'awardCategory')->count(2)->create()->all();
    $winner->forceFill(['stage1_score' => 70, 'stage1_rank' => 2, 'stage1_calculated_at' => now()])->save();
    $qualified = Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $unconfirmedFinalist = Submission::factory()->finalist()->for(categoryWithRubric(), 'awardCategory')->create();

    $judge = judgeAssignedTo($category);
    submitScores($judge, $runnerUp, 60, JudgingStage::Pitching);
    submitScores($judge, $winner, 90, JudgingStage::Pitching);
    submitScores($judge, $winner, 10);
    submitScores($judge, $qualified, 100, JudgingStage::Pitching);
    submitScores(judgeAssignedTo($unconfirmedFinalist->awardCategory), $unconfirmedFinalist, 100, JudgingStage::Pitching);
    submitScores(judgeAssignedTo($category, recused: true), $runnerUp, 100, JudgingStage::Pitching);
    JudgeScore::factory()->pitching()->create([
        'submission_id' => $runnerUp->id,
        'judge_id' => judgeAssignedTo($category)->id,
        'scoring_criterion_id' => $category->assessmentTemplate->criteria()->value('id'),
        'raw_score' => 0,
    ]);

    recalculateStageTwo();

    expect($winner->fresh())
        ->stage2_score->toBe(90.0)
        ->stage2_raw_score->toBe(90.0)
        ->stage2_rank->toBe(1)
        ->stage2_judges_submitted->toBe(1)
        ->stage2_judges_assigned->toBe(2)
        ->stage1_score->toBe(70.0)
        ->stage1_rank->toBe(2)
        ->and($runnerUp->fresh())
        ->stage2_score->toBe(60.0)
        ->stage2_rank->toBe(2)
        ->and($qualified->fresh()->stage2_calculated_at)->toBeNull()
        ->and($unconfirmedFinalist->fresh()->stage2_calculated_at)->toBeNull();
});

test('stage 2 normalizes each judge against their pitching scores only', function () {
    Setting::put(StageScoreCalculator::NORMALIZATION_MIN_SAMPLE_KEY, '2');
    $category = categoryWithRubric(finalistsConfirmed: true);
    [$first, $second] = Submission::factory()->finalist()->for($category, 'awardCategory')->count(2)->create()->all();
    $strictJudge = judgeAssignedTo($category);
    $generousJudge = judgeAssignedTo($category);

    submitScores($strictJudge, $first, 60, JudgingStage::Pitching);
    submitScores($strictJudge, $second, 80, JudgingStage::Pitching);
    submitScores($generousJudge, $first, 90, JudgingStage::Pitching);
    submitScores($generousJudge, $second, 100, JudgingStage::Pitching);
    submitScores($strictJudge, Submission::factory()->qualified()->for(categoryWithRubric(), 'awardCategory')->create(), 0);

    recalculateStageTwo();

    // Both judges put the first finalist one deviation below their mean; overall mean 82.5, deviation √218.75.
    expect($first->fresh()->stage2_score)->toBe(round(82.5 - sqrt(218.75), 4))
        ->and($second->fresh()->stage2_score)->toBe(round(82.5 + sqrt(218.75), 4))
        ->and($first->fresh()->stage2_raw_score)->toBe(75.0);
});

test('a stage 2 recalculation that is already running is refused', function () {
    $finalist = Submission::factory()->finalist()->for(categoryWithRubric(finalistsConfirmed: true), 'awardCategory')->create();
    $lock = Cache::lock(CalculateStageTwoScores::LOCK_KEY, 60);
    $lock->get();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.score-recap.stage-2.recalculate'))
        ->assertInertiaFlash('toast.type', 'error');

    $lock->release();

    expect($finalist->fresh()->stage2_calculated_at)->toBeNull();
});

test('the stage 2 tab lists the confirmed finalists by their pitching rank', function () {
    withoutNormalization();
    $category = categoryWithRubric(finalistsConfirmed: true);
    [$runnerUp, $winner] = Submission::factory()->finalist()->for($category, 'awardCategory')->count(2)->create()->all();
    $runnerUp->forceFill(['stage1_score' => 88, 'stage1_calculated_at' => now()])->save();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    $judge = judgeAssignedTo($category);
    submitScores($judge, $runnerUp, 60, JudgingStage::Pitching);
    submitScores($judge, $winner, 90, JudgingStage::Pitching);

    recalculateStageTwo();

    $this->get(route('admin.score-recap.index', ['stage' => 'pitching', 'category' => $category->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.stage', 'pitching')
            ->has('submissions.data', 2)
            ->where('submissions.data.0.id', $winner->id)
            ->where('submissions.data.0.rank', 1)
            ->where('submissions.data.0.score', 90)
            ->where('submissions.data.1.stage1_score', 88)
            ->where('candidates', null)
            ->whereNot('calculatedAt', null));
});

test('the stage 2 export lists the finalists', function () {
    Excel::fake();
    $this->travelTo(now()->setTimezone('UTC')->setDateTime(2026, 11, 11, 3, 30));
    $category = categoryWithRubric(finalistsConfirmed: true);
    Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.score-recap.export', ['stage' => 'pitching']))
        ->assertOk();

    Excel::assertDownloaded('score-recap-stage-2-20261111-1030.xlsx', function (ScoreRecapExport $export) {
        return $export->query()->count() === 1
            && $export->headings()[1] === 'Stage 2 rank';
    });
});

test('the final score weighs the stage 1 and stage 2 scores and ranks them per category', function () {
    withoutNormalization();
    Setting::put(CalculateStageTwoScores::STAGE_1_WEIGHT_KEY, '40');
    Setting::put(CalculateStageTwoScores::STAGE_2_WEIGHT_KEY, '60');
    $category = categoryWithRubric(finalistsConfirmed: true);
    $pitchWinner = finalistWithScores($category, 70, 90);
    $paperWinner = finalistWithScores($category, 95, 70);
    $tiedWithPitchWinner = finalistWithScores($category, 85, 80);
    $withoutPitching = Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    $withoutPitching->forceFill(['stage1_score' => 99, 'stage1_calculated_at' => now()])->save();

    recalculateStageTwo();

    expect($pitchWinner->fresh())->final_score->toBe(82.0)->final_rank->toBe(1)
        ->and($tiedWithPitchWinner->fresh())->final_score->toBe(82.0)->final_rank->toBe(1)
        ->and($paperWinner->fresh())->final_score->toBe(80.0)->final_rank->toBe(3)
        ->and($withoutPitching->fresh())->final_score->toBeNull()->final_rank->toBeNull();
});

test('recalculating stage 2 is refused while any category has confirmed awards', function () {
    $category = categoryWithRubric(finalistsConfirmed: true);
    $category->forceFill(['awards_confirmed_at' => now()])->save();
    $finalist = finalistWithScores($category, 80, 80);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.score-recap.stage-2.recalculate'))
        ->assertInertiaFlash('toast.message', 'Awards are confirmed for 1 category. Reopen them before recalculating.');

    expect($finalist->fresh()->stage2_calculated_at)->toBeNull();
});

test('the final tab lists the finalists by final rank with their award candidates', function () {
    withoutNormalization();
    $category = categoryWithRubric(finalistsConfirmed: true);
    $runnerUp = finalistWithScores($category, 60, 60);
    $winner = finalistWithScores($category, 90, 90);
    recalculateStageTwo();

    $this->get(route('admin.score-recap.index', ['stage' => 'final', 'category' => $category->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.stage', 'final')
            ->has('submissions.data', 2)
            ->where('submissions.data.0.id', $winner->id)
            ->where('submissions.data.0.rank', 1)
            ->where('submissions.data.0.score', 90)
            ->where('submissions.data.0.stage2_score', 90)
            ->where('submissions.data.0.raw_score', null)
            ->where('candidates', null)
            ->where('awardCandidates.0.id', $winner->id)
            ->where('awardCandidates.0.suggested_award', 'gold')
            ->where('awardCandidates.1.id', $runnerUp->id)
            ->where('awardCandidates.1.suggested_award', 'silver')
            ->where('awardQuota', ['gold' => 1, 'silver' => 2, 'bronze' => 2])
            ->where('weights', ['stage_1' => 50, 'stage_2' => 50])
            ->whereNot('calculatedAt', null));
});

test('the final export lists the finalists with their award', function () {
    Excel::fake();
    $this->travelTo(now()->setTimezone('UTC')->setDateTime(2026, 11, 11, 3, 30));
    $category = categoryWithRubric(finalistsConfirmed: true);
    Submission::factory()->finalist()->for($category, 'awardCategory')->create()
        ->forceFill(['final_score' => 88, 'final_rank' => 1, 'award' => 'gold'])->save();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.score-recap.export', ['stage' => 'final']))
        ->assertOk();

    Excel::assertDownloaded('score-recap-final-20261111-1030.xlsx', function (ScoreRecapExport $export) {
        $row = $export->map($export->query()->sole());

        return $export->query()->count() === 1
            && $export->headings()[1] === 'Final rank'
            && $row[8] === 88.0
            && $row[9] === 'Gold';
    });
});
