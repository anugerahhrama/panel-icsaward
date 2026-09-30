<?php

use App\Enums\JudgingStage;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{stage_1_weight: int, stage_2_weight: int}
 */
function defaultWeights(): array
{
    return ['stage_1_weight' => 50, 'stage_2_weight' => 50];
}

test('judges cannot change the judging stage', function () {
    $this->actingAs(User::factory()->judge()->create())
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'desk_evaluation'])
        ->assertForbidden();

    expect(JudgingStage::current())->toBe(JudgingStage::Closed);
});

test('judging is closed until a stage is opened', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.settings.judging.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/judging')
            ->where('settings.judging_stage', 'closed')
            ->where('settings.normalization_enabled', true)
            ->where('settings.normalization_min_sample', 3)
            ->where('settings.stage_1_weight', 50)
            ->where('settings.stage_2_weight', 50)
            ->where('weightsLocked', false)
            ->has('stages', 3)
            ->where('stages.0.value', 'closed')
            ->where('stages.1.value', 'desk_evaluation')
            ->where('stages.2.value', 'pitching'));
});

test('admins can open desk evaluation', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.judging.update'), [
            'judging_stage' => 'desk_evaluation',
            'normalization_enabled' => '1',
            'normalization_min_sample' => 3,
            ...defaultWeights(),
        ])
        ->assertRedirect(route('admin.settings.judging.edit'))
        ->assertSessionHasNoErrors();

    expect(Setting::get(JudgingStage::SETTING_KEY))->toBe('desk_evaluation')
        ->and(JudgingStage::current())->toBe(JudgingStage::DeskEvaluation);
});

test('admins can open pitching', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'pitching', 'normalization_min_sample' => 3, ...defaultWeights()])
        ->assertSessionHasNoErrors();

    expect(JudgingStage::current())->toBe(JudgingStage::Pitching);
});

test('an unknown judging stage is refused', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'final', 'normalization_min_sample' => 3])
        ->assertSessionHasErrors(['judging_stage' => 'The selected judging stage is invalid.']);

    expect(Setting::get(JudgingStage::SETTING_KEY))->toBeNull();
});

test('admins can turn normalization off and change the minimum sample', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'closed', 'normalization_min_sample' => 5, ...defaultWeights()])
        ->assertSessionHasNoErrors();

    expect(Setting::get('normalization_enabled'))->toBe('0')
        ->and(Setting::get('normalization_min_sample'))->toBe('5');
});

test('the minimum sample must be at least two', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'closed', 'normalization_min_sample' => 1])
        ->assertSessionHasErrors('normalization_min_sample');

    expect(Setting::get('normalization_min_sample'))->toBeNull();
});

test('admins change the final score weights', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'closed', 'normalization_min_sample' => 3, 'stage_1_weight' => 40, 'stage_2_weight' => 60])
        ->assertSessionHasNoErrors();

    expect(Setting::get('stage_1_weight'))->toBe('40')
        ->and(Setting::get('stage_2_weight'))->toBe('60');
});

test('the weights must add up to 100', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'closed', 'normalization_min_sample' => 3, 'stage_1_weight' => 40, 'stage_2_weight' => 50])
        ->assertSessionHasErrors(['stage_1_weight' => 'The Stage 1 and Stage 2 weights must add up to 100.']);

    expect(Setting::get('stage_1_weight'))->toBeNull();
});

test('the weights are frozen once awards are confirmed, but the other settings are not', function () {
    AwardCategory::factory()->create(['finalists_confirmed_at' => now(), 'awards_confirmed_at' => now()]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.judging.update'), ['judging_stage' => 'closed', 'normalization_min_sample' => 3, 'stage_1_weight' => 40, 'stage_2_weight' => 60])
        ->assertSessionHasErrors('stage_1_weight');

    $this->put(route('admin.settings.judging.update'), ['judging_stage' => 'closed', 'normalization_min_sample' => 4, ...defaultWeights()])
        ->assertSessionHasNoErrors();

    expect(Setting::get('stage_1_weight'))->toBe('50')
        ->and(Setting::get('normalization_min_sample'))->toBe('4');

    $this->get(route('admin.settings.judging.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('weightsLocked', true));
});
