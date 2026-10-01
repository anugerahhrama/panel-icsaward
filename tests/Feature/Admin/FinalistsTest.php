<?php

use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use App\Exports\ScoreRecapExport;
use App\Models\AwardCategory;
use App\Models\JudgeScore;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Qualified submissions of the category with a stored Stage 1 rank, in rank order (1, 2, …).
 *
 * @return list<Submission>
 */
function rankedSubmissions(AwardCategory $category, int $count, int $judgesSubmitted = 2): array
{
    return Submission::factory()->qualified()->for($category, 'awardCategory')->count($count)->create()
        ->each(fn (Submission $submission, int $index) => $submission->forceFill([
            'stage1_score' => 90 - $index,
            'stage1_raw_score' => 90 - $index,
            'stage1_rank' => $index + 1,
            'stage1_judges_submitted' => $judgesSubmitted,
            'stage1_judges_assigned' => 2,
            'stage1_calculated_at' => now(),
        ])->save())
        ->all();
}

/**
 * @param  list<Submission>  $submissions
 */
function confirmFinalists(User $user, AwardCategory $category, array $submissions): TestResponse
{
    return test()->actingAs($user)->post(route('admin.score-recap.finalists.store', $category), [
        'submission_ids' => array_map(fn (Submission $submission) => $submission->id, $submissions),
    ]);
}

test('admins confirm the finalists of a category and freeze it', function () {
    $admin = User::factory()->admin()->create();
    $category = AwardCategory::factory()->create();
    $submissions = rankedSubmissions($category, 6);
    $finalists = array_slice($submissions, 0, 5);

    confirmFinalists($admin, $category, $finalists)->assertInertiaFlash('toast.type', 'success');

    expect(collect($submissions)->map(fn (Submission $submission) => $submission->fresh()->status)->all())
        ->toBe([...array_fill(0, 5, SubmissionStatus::Finalist), SubmissionStatus::Qualified])
        ->and($category->fresh())
        ->finalists_confirmed_at->not->toBeNull()
        ->finalists_confirmed_by->toBe($admin->id);
});

test('an incomplete submission may become a finalist', function () {
    $category = AwardCategory::factory()->create();
    [$incomplete] = rankedSubmissions($category, 1, judgesSubmitted: 1);

    confirmFinalists(User::factory()->admin()->create(), $category, [$incomplete])
        ->assertInertiaFlash('toast.type', 'success');

    expect($incomplete->fresh()->status)->toBe(SubmissionStatus::Finalist);
});

test('more than five finalists are refused', function () {
    $category = AwardCategory::factory()->create();

    confirmFinalists(User::factory()->admin()->create(), $category, rankedSubmissions($category, 6))
        ->assertSessionHasErrors('submission_ids');

    expect($category->fresh()->finalists_confirmed_at)->toBeNull();
});

test('only ranked qualified submissions of the category can become finalists', function (Closure $arrange) {
    $category = AwardCategory::factory()->create();
    [$ranked] = rankedSubmissions($category, 1);

    confirmFinalists(User::factory()->admin()->create(), $category, [$ranked, $arrange($category)])
        ->assertInertiaFlash('toast.type', 'error');

    expect($ranked->fresh()->status)->toBe(SubmissionStatus::Qualified)
        ->and($category->fresh()->finalists_confirmed_at)->toBeNull();
})->with([
    'another category' => fn (AwardCategory $category) => rankedSubmissions(AwardCategory::factory()->create(), 1)[0],
    'not qualified' => fn (AwardCategory $category) => Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create(),
    'without a rank' => fn (AwardCategory $category) => Submission::factory()->qualified()->for($category, 'awardCategory')->create(),
]);

test('a category is confirmed only once', function () {
    $admin = User::factory()->admin()->create();
    $category = AwardCategory::factory()->create();
    [$first, $second] = rankedSubmissions($category, 2);
    confirmFinalists($admin, $category, [$first]);

    confirmFinalists($admin, $category, [$second])->assertInertiaFlash('toast.type', 'error');

    expect($second->fresh()->status)->toBe(SubmissionStatus::Qualified);
});

test('finalists cannot be confirmed while desk evaluation is open', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);
    $category = AwardCategory::factory()->create();
    [$submission] = rankedSubmissions($category, 1);

    confirmFinalists(User::factory()->admin()->create(), $category, [$submission])
        ->assertInertiaFlash('toast.type', 'error');

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Qualified);
});

test('judges and participants cannot confirm or reopen finalists', function (string $role) {
    $user = $role === 'judge' ? User::factory()->judge()->create() : User::factory()->create();
    $category = AwardCategory::factory()->create();

    confirmFinalists($user, $category, rankedSubmissions($category, 1))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.score-recap.finalists.destroy', $category))->assertForbidden();
})->with(['judge', 'participant']);

test('recalculating is refused while any category has confirmed finalists', function () {
    $admin = User::factory()->admin()->create();
    $category = AwardCategory::factory()->create();
    [$finalist, $other] = rankedSubmissions($category, 2);
    confirmFinalists($admin, $category, [$finalist]);

    $this->actingAs($admin)
        ->post(route('admin.score-recap.recalculate'))
        ->assertInertiaFlash('toast.type', 'error');

    expect($other->fresh())
        ->stage1_rank->toBe(2)
        ->stage1_score->toEqual(89.0);
});

test('superadmins reopen the finalists, which unfreezes the results', function () {
    $category = AwardCategory::factory()->create();
    [$finalist] = rankedSubmissions($category, 1);
    confirmFinalists(User::factory()->admin()->create(), $category, [$finalist]);
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->delete(route('admin.score-recap.finalists.destroy', $category))
        ->assertInertiaFlash('toast.type', 'success');

    expect($finalist->fresh()->status)->toBe(SubmissionStatus::Qualified)
        ->and($category->fresh())
        ->finalists_confirmed_at->toBeNull()
        ->finalists_confirmed_by->toBeNull();

    $this->post(route('admin.score-recap.recalculate'))->assertInertiaFlash('toast.type', 'success');
});

test('reopening the finalists removes their pitching slots but keeps the session', function () {
    $category = AwardCategory::factory()->create();
    [$finalist] = rankedSubmissions($category, 1);
    confirmFinalists(User::factory()->admin()->create(), $category, [$finalist]);
    $session = PitchingSession::factory()->for($category, 'awardCategory')->create();
    PitchingSlot::factory()->for($session)->for($finalist)->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.score-recap.finalists.destroy', $category))
        ->assertInertiaFlash('toast.type', 'success');

    expect(PitchingSlot::query()->exists())->toBeFalse()
        ->and($session->fresh())->not->toBeNull();
});

test('admins cannot reopen the finalists', function () {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.score-recap.finalists.destroy', $category))
        ->assertForbidden();

    expect($category->fresh()->finalists_confirmed_at)->not->toBeNull();
});

test('finalists with pitching scores cannot be reopened', function () {
    $category = categoryWithRubric();
    [$finalist] = rankedSubmissions($category, 1);
    confirmFinalists(User::factory()->admin()->create(), $category, [$finalist]);
    JudgeScore::factory()->create([
        'submission_id' => $finalist->id,
        'judge_id' => judgeAssignedTo($category)->id,
        'scoring_criterion_id' => $category->assessmentTemplate->criteria()->value('id'),
        'stage' => JudgingStage::Pitching,
    ]);

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.score-recap.finalists.destroy', $category))
        ->assertInertiaFlash('toast.type', 'error');

    expect($finalist->fresh()->status)->toBe(SubmissionStatus::Finalist);
});

test('the score recap keeps finalists and lists the candidates of the chosen category', function () {
    $admin = User::factory()->admin()->create();
    $category = AwardCategory::factory()->create();
    [$finalist, $runnerUp] = rankedSubmissions($category, 2);
    confirmFinalists($admin, $category, [$finalist]);

    $this->get(route('admin.score-recap.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('candidates', null)
            ->has('submissions.data', 2));

    $this->get(route('admin.score-recap.index', ['category' => $category->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.data.0.id', $finalist->id)
            ->where('submissions.data.0.is_finalist', true)
            ->where('submissions.data.1.is_finalist', false)
            ->has('candidates', 2)
            ->where('candidates.0.id', $finalist->id)
            ->where('candidates.1.id', $runnerUp->id)
            ->where('categories.0.finalists_count', 1)
            ->whereNot('categories.0.finalists_confirmed_at', null)
            ->where('maxFinalists', 5)
            ->where('canReopenFinalists', false));
});

test('the score recap export marks the finalists', function () {
    Excel::fake();
    $this->travelTo(now()->setTimezone('UTC')->setDateTime(2026, 11, 2, 3, 0));
    $admin = User::factory()->admin()->create();
    $category = AwardCategory::factory()->create();
    [$finalist] = rankedSubmissions($category, 1);
    confirmFinalists($admin, $category, [$finalist]);

    $this->get(route('admin.score-recap.export'))->assertOk();

    Excel::assertDownloaded('score-recap-stage-1-20261102-1000.xlsx', fn (ScoreRecapExport $export) => $export->map($export->query()->sole())[10] === 'Yes');
});

test('judges still see the submissions that became finalists', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    [$finalist] = rankedSubmissions($category, 1);
    confirmFinalists(User::factory()->admin()->create(), $category, [$finalist]);

    $this->actingAs($judge->user)
        ->get(route('judge.submissions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('submissions.data', 1)
            ->where('submissions.data.0.uuid', $finalist->uuid));
});

test('announced finalists cannot be reopened', function () {
    $category = AwardCategory::factory()->finalistsAnnounced()->create();
    $finalist = Submission::factory()->finalist()->for($category, 'awardCategory')->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.score-recap.finalists.destroy', $category))
        ->assertInertiaFlash('toast.message', 'The finalists of this category are already announced, so they cannot be reopened.');

    expect($finalist->fresh()->status)->toBe(SubmissionStatus::Finalist);
});
