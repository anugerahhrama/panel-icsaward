<?php

use App\Enums\JudgingStage;
use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use App\Models\Judge;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Every locked request: the HTTP method, URL, payload, the index it returns to and a check that nothing changed.
 *
 * @return array<string, Closure(): array{0: string, 1: string, 2: array<string, mixed>, 3: string, 4: Closure(): bool}>
 */
dataset('locked setup requests', [
    'create a category' => fn () => [
        'post', route('admin.categories.store'), ['name' => 'New', 'applicant_type' => 'individual', 'sort_order' => 1],
        'admin.categories.index', fn () => AwardCategory::count() === 0,
    ],
    'update a category' => function () {
        $category = AwardCategory::factory()->create(['name' => 'Old']);

        return [
            'post', route('admin.categories.update', $category), ['name' => 'New', 'applicant_type' => 'individual', 'sort_order' => 1],
            'admin.categories.index', fn () => $category->refresh()->name === 'Old',
        ];
    },
    'delete a category' => function () {
        $category = AwardCategory::factory()->create();

        return ['delete', route('admin.categories.destroy', $category), [], 'admin.categories.index', fn () => AwardCategory::count() === 1];
    },
    'open the new template form' => fn () => [
        'get', route('admin.assessment-templates.create'), [], 'admin.assessment-templates.index', fn () => true,
    ],
    'create a template' => fn () => [
        'post', route('admin.assessment-templates.store'), ['name' => 'New', 'criteria' => [['aspect' => 'A', 'criteria' => 'B', 'weight' => 100]]],
        'admin.assessment-templates.index', fn () => AssessmentTemplate::count() === 0,
    ],
    'update a template' => function () {
        $template = AssessmentTemplate::factory()->create(['name' => 'Old']);

        return [
            'put', route('admin.assessment-templates.update', $template), ['name' => 'New', 'criteria' => [['aspect' => 'A', 'criteria' => 'B', 'weight' => 100]]],
            'admin.assessment-templates.index', fn () => $template->refresh()->name === 'Old',
        ];
    },
    'delete a template' => function () {
        $template = AssessmentTemplate::factory()->create();

        return ['delete', route('admin.assessment-templates.destroy', $template), [], 'admin.assessment-templates.index', fn () => AssessmentTemplate::count() === 1];
    },
    'delete a judge' => function () {
        $judge = Judge::factory()->create();

        return ['delete', route('admin.judges.destroy', $judge), [], 'admin.judges.index', fn () => Judge::count() === 1];
    },
]);

test('admins cannot change the judging setup while a stage is active', function (array $request) {
    [$method, $url, $payload, $index, $isUnchanged] = $request;
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);

    $this->actingAs(User::factory()->admin()->create())
        ->{$method}($url, $payload)
        ->assertRedirect(route($index))
        ->assertInertiaFlash('toast.type', 'error');

    expect($isUnchanged())->toBeTrue();
})->with('locked setup requests');

test('superadmins can still change the judging setup while a stage is active', function (array $request) {
    [$method, $url, $payload, $index, $isUnchanged] = $request;
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);

    $response = $this->actingAs(User::factory()->superadmin()->create())->{$method}($url, $payload);

    if ($method === 'get') {
        $response->assertOk();
    } else {
        $response->assertRedirect(route($index))->assertSessionHasNoErrors()->assertInertiaFlash('toast.type', 'success');
        expect($isUnchanged())->toBeFalse();
    }
})->with('locked setup requests');

test('the setup pages tell whether the user can change the judging setup', function (string $role, string $stage, bool $canManage) {
    Setting::put(JudgingStage::SETTING_KEY, $stage);
    $this->actingAs(User::factory()->{$role}()->create());

    foreach (['admin.categories.index', 'admin.assessment-templates.index', 'admin.judges.index'] as $route) {
        $this->get(route($route))
            ->assertInertia(fn ($page) => $page->where('canManageJudgingSetup', $canManage));
    }
})->with([
    'admin, closed' => ['admin', 'closed', true],
    'admin, active' => ['admin', 'desk_evaluation', false],
    'superadmin, active' => ['superadmin', 'desk_evaluation', true],
]);

test('while locked, admins can edit a judge profile but not its assignments', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);
    $category = AwardCategory::factory()->create();
    $judge = Judge::factory()->assignedTo([$category->id])->create(['name' => 'Old']);
    $admin = User::factory()->admin()->create();
    $profile = [
        'name' => 'New', 'position' => 'Director', 'sort_order' => 1, 'has_account' => false,
    ];

    $this->actingAs($admin)
        ->post(route('admin.judges.update', $judge), [...$profile, 'assignments' => [['award_category_id' => $category->id, 'is_recused' => false]]])
        ->assertSessionHasNoErrors();

    expect($judge->refresh()->name)->toBe('New');

    $this->actingAs($admin)
        ->post(route('admin.judges.update', $judge), [...$profile, 'assignments' => [['award_category_id' => $category->id, 'is_recused' => true]]])
        ->assertSessionHasErrors('assignments');

    $this->actingAs($admin)
        ->post(route('admin.judges.store'), [...$profile, 'assignments' => [['award_category_id' => $category->id, 'is_recused' => false]]])
        ->assertSessionHasErrors('assignments');

    expect($judge->categories()->sole()->getRelationValue('pivot')->getAttribute('is_recused'))->toBeFalsy()
        ->and(Judge::count())->toBe(1);
});
