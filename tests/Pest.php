<?php

use App\Enums\JudgingStage;
use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use App\Models\Judge;
use App\Models\JudgeScore;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * An award category whose assessment template has five criteria (weights 15/20/35/15/15), optionally with confirmed finalists.
 */
function categoryWithRubric(bool $finalistsConfirmed = false): AwardCategory
{
    return AwardCategory::factory()
        ->for(AssessmentTemplate::factory()->withCriteria())
        ->when($finalistsConfirmed, fn ($factory) => $factory->finalistsConfirmed())
        ->create();
}

/**
 * A judge with a login account, assigned to the given category.
 */
function judgeAssignedTo(AwardCategory $category, bool $recused = false): Judge
{
    return Judge::factory()->withAccount()->assignedTo([$category->id], $recused)->create();
}

/**
 * A desk evaluation score the judge gave on a qualified submission of the category, on its first criterion.
 */
function scoreGivenBy(Judge $judge, AwardCategory $category): JudgeScore
{
    return JudgeScore::factory()->create([
        'submission_id' => Submission::factory()->qualified()->for($category, 'awardCategory'),
        'judge_id' => $judge->id,
        'scoring_criterion_id' => $category->assessmentTemplate->criteria()->value('id'),
    ]);
}

/**
 * Submitted scores (desk evaluation unless another stage is given) from the judge on every criterion of the submission's rubric: one raw score for all
 * criteria (so the weighted score equals it), or one per criterion in rubric order.
 *
 * @param  int|list<int>  $raw
 */
function submitScores(Judge $judge, Submission $submission, int|array $raw, JudgingStage $stage = JudgingStage::DeskEvaluation): void
{
    $submission->awardCategory->assessmentTemplate->criteria()->orderBy('sort_order')->pluck('id')
        ->each(fn (int $criterionId, int $index) => JudgeScore::factory()->submitted()->create([
            'submission_id' => $submission->id,
            'judge_id' => $judge->id,
            'scoring_criterion_id' => $criterionId,
            'raw_score' => is_array($raw) ? $raw[$index] : $raw,
            'stage' => $stage,
        ]));
}
