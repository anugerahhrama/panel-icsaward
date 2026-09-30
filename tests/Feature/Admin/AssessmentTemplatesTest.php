<?php

use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use App\Models\ScoringCriterion;
use App\Models\User;

/**
 * @param  list<int>  $weights
 * @return array<string, mixed>
 */
function validTemplate(array $weights = [40, 60]): array
{
    return [
        'name' => 'Environmental Assessment',
        'description' => 'Rubric for environmental categories.',
        'criteria' => array_map(fn (int $weight, int $index) => [
            'aspect' => "Aspect {$index}",
            'criteria' => "Criteria {$index}",
            'description' => "Description {$index}",
            'weight' => $weight,
        ], $weights, array_keys($weights)),
    ];
}

test('participants cannot manage assessment templates', function () {
    $template = AssessmentTemplate::factory()->create();
    $participant = User::factory()->create();

    $this->actingAs($participant)->get(route('admin.assessment-templates.index'))->assertForbidden();
    $this->actingAs($participant)->post(route('admin.assessment-templates.store'), validTemplate())->assertForbidden();
    $this->actingAs($participant)->delete(route('admin.assessment-templates.destroy', $template))->assertForbidden();

    expect(AssessmentTemplate::count())->toBe(1);
});

test('admins see templates with their total weight and usage', function () {
    $template = AssessmentTemplate::factory()->withCriteria()->create();
    AwardCategory::factory()->for($template)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.assessment-templates.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/assessment-templates/index')
            ->has('templates', 1)
            ->where('templates.0.criteria_count', 5)
            ->where('templates.0.criteria_sum_weight', 100)
            ->where('templates.0.categories_count', 1));
});

test('admins can create a template with ordered criteria', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.assessment-templates.store'), validTemplate([40, 60]))
        ->assertRedirect(route('admin.assessment-templates.index'))
        ->assertSessionHasNoErrors();

    $template = AssessmentTemplate::sole();

    expect($template->criteria->pluck('weight')->all())->toBe([40, 60])
        ->and($template->criteria->pluck('sort_order')->all())->toBe([1, 2])
        ->and($template->criteria->first()->aspect)->toBe('Aspect 0');
});

test('criteria weights must add up to 100', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.assessment-templates.store'), validTemplate([40, 50]))
        ->assertSessionHasErrors('criteria');

    expect(AssessmentTemplate::count())->toBe(0);
});

test('every criterion needs an aspect, criteria and a weight between 1 and 100', function () {
    $payload = validTemplate([0, 100]);
    $payload['criteria'][1]['aspect'] = '';

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.assessment-templates.store'), $payload)
        ->assertSessionHasErrors(['criteria.0.weight', 'criteria.1.aspect'])
        ->assertSessionDoesntHaveErrors('criteria');

    expect(AssessmentTemplate::count())->toBe(0);
});

test('updating a template keeps criterion ids, removes dropped rows and follows the new order', function () {
    $template = AssessmentTemplate::factory()->create(['name' => 'Environmental Assessment']);
    [$first, $second, $dropped] = ScoringCriterion::factory()->for($template)->count(3)->create(['weight' => 30]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.assessment-templates.update', $template), [
            'name' => 'Environmental Assessment',
            'criteria' => [
                ['id' => $second->id, 'aspect' => 'Impact', 'criteria' => 'Baseline', 'weight' => 50],
                ['id' => $first->id, 'aspect' => 'Strategy', 'criteria' => 'Target', 'weight' => 30],
                ['aspect' => 'Innovation', 'criteria' => 'Novelty', 'weight' => 20],
            ],
        ])
        ->assertRedirect(route('admin.assessment-templates.index'))
        ->assertSessionHasNoErrors();

    $criteria = $template->criteria()->get();

    expect($criteria->pluck('aspect')->all())->toBe(['Impact', 'Strategy', 'Innovation'])
        ->and($criteria[0]->id)->toBe($second->id)
        ->and($criteria[1]->id)->toBe($first->id)
        ->and(ScoringCriterion::find($dropped->id))->toBeNull();
});

test('criteria from another template cannot be claimed on update', function () {
    $template = AssessmentTemplate::factory()->create();
    $foreign = ScoringCriterion::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.assessment-templates.update', $template), [
            'name' => $template->name,
            'criteria' => [['id' => $foreign->id, 'aspect' => 'Impact', 'criteria' => 'Baseline', 'weight' => 100]],
        ])
        ->assertSessionHasErrors('criteria.0.id');

    expect($foreign->refresh()->aspect)->not->toBe('Impact');
});

test('admins can delete an unused template together with its criteria', function () {
    $template = AssessmentTemplate::factory()->withCriteria()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.assessment-templates.destroy', $template))
        ->assertRedirect(route('admin.assessment-templates.index'));

    expect(AssessmentTemplate::count())->toBe(0)
        ->and(ScoringCriterion::count())->toBe(0);
});

test('templates used by a category cannot be deleted', function () {
    $template = AssessmentTemplate::factory()->create();
    AwardCategory::factory()->for($template)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.assessment-templates.destroy', $template))
        ->assertRedirect(route('admin.assessment-templates.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(AssessmentTemplate::count())->toBe(1);
});

test('criteria that judges have scored cannot be removed, even by a superadmin', function () {
    $category = categoryWithRubric();
    $criteria = $category->assessmentTemplate->criteria()->get();
    scoreGivenBy(judgeAssignedTo($category), $category);

    $this->actingAs(User::factory()->superadmin()->create())
        ->put(route('admin.assessment-templates.update', $category->assessmentTemplate), [
            'name' => $category->assessmentTemplate->name,
            'criteria' => $criteria->skip(1)->values()->map(fn (ScoringCriterion $criterion, int $index) => [
                'id' => $criterion->id,
                'aspect' => $criterion->aspect,
                'criteria' => $criterion->criteria,
                'weight' => $index === 0 ? $criterion->weight + $criteria[0]->weight : $criterion->weight,
            ])->all(),
        ])
        ->assertSessionHasErrors(['criteria' => 'Criteria that already have judge scores cannot be removed.']);

    expect(ScoringCriterion::count())->toBe(5);
});

test('unused templates whose criteria have scores cannot be deleted', function () {
    $category = categoryWithRubric();
    scoreGivenBy(judgeAssignedTo($category), $category);
    $template = $category->assessmentTemplate;
    $category->assessmentTemplate()->dissociate()->save();

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.assessment-templates.destroy', $template))
        ->assertRedirect(route('admin.assessment-templates.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(AssessmentTemplate::count())->toBe(1);
});
