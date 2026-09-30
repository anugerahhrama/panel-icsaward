<?php

use App\Enums\JudgingStage;
use App\Models\AwardCategory;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A valid schedule payload for the given finalists, starting 09:00 WIB with one slot per finalist every 20 minutes.
 *
 * @param  list<Submission>  $finalists
 * @return array<string, mixed>
 */
function pitchingSchedule(array $finalists, array $overrides = []): array
{
    return [
        'date' => '2026-11-06',
        'start_time' => '09:00',
        'location' => 'Ballroom A',
        'meeting_link' => 'https://meet.example.com/icsa',
        'slots' => array_map(fn (Submission $finalist, int $index): array => [
            'submission_id' => $finalist->id,
            'starts_at' => sprintf('09:%02d', $index * 20),
        ], $finalists, array_keys($finalists)),
        ...$overrides,
    ];
}

/**
 * @return array{0: AwardCategory, 1: list<Submission>}
 */
function categoryWithFinalists(int $count = 2): array
{
    $category = AwardCategory::factory()->finalistsConfirmed()->create();

    return [$category, Submission::factory()->finalist()->for($category, 'awardCategory')->count($count)->create()->all()];
}

test('admins see every category with its pitching session', function () {
    [$category, $finalists] = categoryWithFinalists();
    $session = PitchingSession::factory()->for($category, 'awardCategory')->create();
    PitchingSlot::factory()->for($session)->for($finalists[0])->create();
    $unconfirmed = AwardCategory::factory()->create(['sort_order' => $category->sort_order + 1]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pitching.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pitching/index')
            ->has('categories', 2)
            ->where('categories.0.id', $category->id)
            ->where('categories.0.finalists_count', 2)
            ->where('categories.0.session.slots_count', 1)
            ->where('categories.0.session.location', $session->location)
            ->where('categories.1.id', $unconfirmed->id)
            ->where('categories.1.finalists_confirmed_at', null)
            ->where('categories.1.session', null)
            ->where('canManageJudgingSetup', true));
});

test('judges and participants cannot open the pitching pages', function (string $role) {
    $user = $role === 'judge' ? User::factory()->judge()->create() : User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.pitching.index'))
        ->assertForbidden();
})->with(['judge', 'participant']);

test('the schedule of a category without confirmed finalists cannot be opened', function () {
    $category = AwardCategory::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pitching.edit', $category))
        ->assertRedirect(route('admin.pitching.index'))
        ->assertInertiaFlash('toast.type', 'error');
});

test('the schedule page shows the session and slots in WIB', function () {
    [$category, [$first, $second]] = categoryWithFinalists();
    $first->forceFill(['stage1_rank' => 2])->save();
    $second->forceFill(['stage1_rank' => 1])->save();
    $session = PitchingSession::factory()->for($category, 'awardCategory')->create(['scheduled_at' => '2026-11-06 02:00:00']);
    PitchingSlot::factory()->for($session)->for($first)->create(['starts_at' => '2026-11-06 02:20:00']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.pitching.edit', $category))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/pitching/edit')
            ->where('session.date', '2026-11-06')
            ->where('session.start_time', '09:00')
            ->has('finalists', 2)
            ->where('finalists.0.id', $second->id)
            ->where('finalists.0.starts_at', null)
            ->where('finalists.1.id', $first->id)
            ->where('finalists.1.starts_at', '09:20'));
});

test('admins save the pitching session and slots, read as WIB', function () {
    [$category, [$first, $second]] = categoryWithFinalists();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pitching.update', $category), pitchingSchedule([$first, $second]))
        ->assertRedirect(route('admin.pitching.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $session = $category->pitchingSession()->sole();

    expect($session)
        ->scheduled_at->toDateTimeString()->toBe('2026-11-06 02:00:00')
        ->location->toBe('Ballroom A')
        ->meeting_link->toBe('https://meet.example.com/icsa')
        ->and($first->pitchingSlot->starts_at->toDateTimeString())->toBe('2026-11-06 02:00:00')
        ->and($second->pitchingSlot->starts_at->toDateTimeString())->toBe('2026-11-06 02:20:00');
});

test('saving again updates the session and removes slots without a time', function () {
    [$category, [$first, $second]] = categoryWithFinalists();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put(route('admin.pitching.update', $category), pitchingSchedule([$first, $second]));

    $this->put(route('admin.pitching.update', $category), pitchingSchedule([$first, $second], [
        'date' => '2026-11-07',
        'meeting_link' => null,
        'slots' => [
            ['submission_id' => $first->id, 'starts_at' => '10:00'],
            ['submission_id' => $second->id, 'starts_at' => null],
        ],
    ]))->assertSessionHasNoErrors();

    expect(PitchingSession::query()->count())->toBe(1)
        ->and($category->pitchingSession->meeting_link)->toBeNull()
        ->and($first->fresh()->pitchingSlot->starts_at->toDateTimeString())->toBe('2026-11-07 03:00:00')
        ->and($second->fresh()->pitchingSlot)->toBeNull();
});

test('only the confirmed finalists of the category can be scheduled', function (Closure $makeSubmission) {
    [$category, [$finalist]] = categoryWithFinalists(1);
    $other = $makeSubmission($category);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pitching.update', $category), pitchingSchedule([$finalist, $other]))
        ->assertSessionHasErrors('slots');

    expect(PitchingSession::query()->exists())->toBeFalse();
})->with([
    'a qualified submission' => fn (AwardCategory $category) => Submission::factory()->qualified()->for($category, 'awardCategory')->create(),
    'a finalist of another category' => fn (AwardCategory $category) => Submission::factory()->finalist()->create(),
]);

test('a category without confirmed finalists cannot be scheduled', function () {
    $category = AwardCategory::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pitching.update', $category), pitchingSchedule([]))
        ->assertSessionHasErrors('schedule');
});

test('the schedule is validated', function (array $overrides, string $error) {
    [$category, $finalists] = categoryWithFinalists();

    $slots = [
        ['submission_id' => $finalists[0]->id, 'starts_at' => '09:00'],
        ['submission_id' => $finalists[1]->id, 'starts_at' => '09:20'],
    ];

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pitching.update', $category), pitchingSchedule($finalists, [
            'slots' => array_replace_recursive($slots, $overrides['slots'] ?? []),
            ...array_diff_key($overrides, ['slots' => true]),
        ]))
        ->assertSessionHasErrors($error);
})->with([
    'no location or meeting link' => [['location' => null, 'meeting_link' => null], 'location'],
    'invalid meeting link' => [['meeting_link' => 'not a url'], 'meeting_link'],
    'slot before the session' => [['slots' => [1 => ['starts_at' => '08:30']]], 'slots.1.starts_at'],
    'two slots at the same time' => [['slots' => [1 => ['starts_at' => '09:00']]], 'slots.1.starts_at'],
]);

test('admins cannot change the schedule while a judging stage is active, superadmins can', function () {
    [$category, $finalists] = categoryWithFinalists();
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.pitching.update', $category), pitchingSchedule($finalists))
        ->assertRedirect(route('admin.pitching.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(PitchingSession::query()->exists())->toBeFalse();

    $this->actingAs(User::factory()->superadmin()->create())
        ->put(route('admin.pitching.update', $category), pitchingSchedule($finalists))
        ->assertInertiaFlash('toast.type', 'success');

    expect(PitchingSession::query()->exists())->toBeTrue();
});

test('admins delete the pitching schedule with its slots', function () {
    [$category, [$finalist]] = categoryWithFinalists(1);
    $session = PitchingSession::factory()->for($category, 'awardCategory')->create();
    PitchingSlot::factory()->for($session)->for($finalist)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.pitching.destroy', $category))
        ->assertRedirect(route('admin.pitching.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(PitchingSession::query()->exists())->toBeFalse()
        ->and(PitchingSlot::query()->exists())->toBeFalse();
});
