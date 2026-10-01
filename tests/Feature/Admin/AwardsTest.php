<?php

use App\Enums\Award;
use App\Enums\JudgingStage;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Finalists of a category with confirmed finalists and a stored final rank, in rank order (1, 2, …).
 *
 * @return list<Submission>
 */
function rankedFinalists(AwardCategory $category, int $count): array
{
    return Submission::factory()->finalist()->for($category, 'awardCategory')->count($count)->create()
        ->each(fn (Submission $submission, int $index) => $submission->forceFill([
            'final_score' => 90 - $index,
            'final_rank' => $index + 1,
            'final_calculated_at' => now(),
        ])->save())
        ->all();
}

/**
 * @param  array<int, string>  $awards  submission id => award
 */
function confirmAwards(User $user, AwardCategory $category, array $awards): TestResponse
{
    return test()->actingAs($user)->post(route('admin.score-recap.awards.store', $category), [
        'awards' => collect($awards)->map(fn (string $award, int $id) => ['submission_id' => $id, 'award' => $award])->values()->all(),
    ]);
}

test('admins confirm the awards of a category and freeze it', function () {
    $admin = User::factory()->admin()->create();
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$first, $second, $third, $fourth] = rankedFinalists($category, 4);

    confirmAwards($admin, $category, [
        $first->id => 'gold',
        $second->id => 'silver',
        $third->id => 'silver',
    ])->assertInertiaFlash('toast.message', "3 awards confirmed for {$category->name}.");

    expect($first->fresh()->award)->toBe(Award::Gold)
        ->and($second->fresh()->award)->toBe(Award::Silver)
        ->and($third->fresh()->award)->toBe(Award::Silver)
        ->and($fourth->fresh()->award)->toBeNull()
        ->and($category->fresh())
        ->awards_confirmed_at->not->toBeNull()
        ->awards_confirmed_by->toBe($admin->id);
});

test('the committee may give awards against the suggested rank', function () {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$first, $second] = rankedFinalists($category, 2);

    confirmAwards(User::factory()->admin()->create(), $category, [$first->id => 'silver', $second->id => 'gold'])
        ->assertInertiaFlash('toast.type', 'success');

    expect($second->fresh()->award)->toBe(Award::Gold);
});

test('awards beyond the quota are refused', function (array $awards, string $message) {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    $finalists = rankedFinalists($category, count($awards));
    $payload = array_combine(array_map(fn (Submission $submission) => $submission->id, $finalists), $awards);

    confirmAwards(User::factory()->admin()->create(), $category, $payload)
        ->assertInertiaFlash('toast.message', $message);

    expect($category->fresh()->awards_confirmed_at)->toBeNull()
        ->and(Submission::query()->whereNotNull('award')->exists())->toBeFalse();
})->with([
    'two gold' => [['gold', 'gold'], 'A category can give at most 1 Gold.'],
    'three silver' => [['gold', 'silver', 'silver', 'silver'], 'A category can give at most 2 Silver.'],
]);

test('only finalists of the category with a final score can receive an award', function (Closure $arrange) {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$finalist] = rankedFinalists($category, 1);
    $other = $arrange($category);

    confirmAwards(User::factory()->admin()->create(), $category, [$finalist->id => 'gold', $other->id => 'silver'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($category->fresh()->awards_confirmed_at)->toBeNull()
        ->and($finalist->fresh()->award)->toBeNull();
})->with([
    'unranked finalist' => fn (AwardCategory $category) => Submission::factory()->finalist()->for($category, 'awardCategory')->create(),
    'qualified submission' => fn (AwardCategory $category) => Submission::factory()->qualified()->for($category, 'awardCategory')->create(['final_rank' => 2]),
    'finalist of another category' => fn () => rankedFinalists(AwardCategory::factory()->finalistsConfirmed()->create(), 1)[0],
]);

test('a category without confirmed finalists cannot confirm awards', function () {
    $category = AwardCategory::factory()->create();
    [$finalist] = rankedFinalists($category, 1);

    confirmAwards(User::factory()->admin()->create(), $category, [$finalist->id => 'gold'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($category->fresh()->awards_confirmed_at)->toBeNull();
});

test('a category is confirmed only once', function () {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$first, $second] = rankedFinalists($category, 2);
    $admin = User::factory()->admin()->create();
    confirmAwards($admin, $category, [$first->id => 'gold']);

    confirmAwards($admin, $category, [$second->id => 'gold'])
        ->assertInertiaFlash('toast.message', 'The awards for this category are already confirmed, or its finalists are not.');

    expect($first->fresh()->award)->toBe(Award::Gold)
        ->and($second->fresh()->award)->toBeNull();
});

test('awards cannot be confirmed while pitching is open', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::Pitching->value);
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$finalist] = rankedFinalists($category, 1);

    confirmAwards(User::factory()->admin()->create(), $category, [$finalist->id => 'gold'])
        ->assertInertiaFlash('toast.type', 'error');

    expect($category->fresh()->awards_confirmed_at)->toBeNull();
});

test('superadmins reopen the awards, which clears them', function () {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$finalist] = rankedFinalists($category, 1);
    confirmAwards(User::factory()->admin()->create(), $category, [$finalist->id => 'gold']);

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.score-recap.awards.destroy', $category))
        ->assertInertiaFlash('toast.type', 'success');

    expect($finalist->fresh()->award)->toBeNull()
        ->and($category->fresh())
        ->awards_confirmed_at->toBeNull()
        ->awards_confirmed_by->toBeNull();
});

test('admins cannot reopen the awards', function () {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$finalist] = rankedFinalists($category, 1);
    confirmAwards(User::factory()->admin()->create(), $category, [$finalist->id => 'gold']);

    $this->delete(route('admin.score-recap.awards.destroy', $category))->assertForbidden();

    expect($finalist->fresh()->award)->toBe(Award::Gold);
});

test('judges and participants cannot confirm or reopen awards', function (string $role) {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    [$finalist] = rankedFinalists($category, 1);
    $user = $role === 'judge' ? User::factory()->judge()->create() : User::factory()->create();

    confirmAwards($user, $category, [$finalist->id => 'gold'])->assertForbidden();
    $this->delete(route('admin.score-recap.awards.destroy', $category))->assertForbidden();

    expect($category->fresh()->awards_confirmed_at)->toBeNull();
})->with(['judge', 'participant']);

test('awards cannot be reopened once the winners are announced', function () {
    $category = AwardCategory::factory()->winnersAnnounced()->create();
    $winner = Submission::factory()->finalist()->for($category, 'awardCategory')->create(['award' => Award::Gold]);

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.score-recap.awards.destroy', $category))
        ->assertInertiaFlash('toast.message', 'The winners of this category are already announced, so the awards cannot be reopened.');

    expect($winner->fresh()->award)->toBe(Award::Gold);
});
