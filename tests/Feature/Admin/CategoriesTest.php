<?php

use App\Enums\ApplicantType;
use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function validCategory(): array
{
    return [
        'name' => 'Best Community Initiative',
        'description' => 'Programs driven by the community.',
        'applicant_type' => 'individual',
        'sort_order' => 3,
    ];
}

test('participants cannot manage categories', function () {
    $category = AwardCategory::factory()->create();
    $participant = User::factory()->create();

    $this->actingAs($participant)->get(route('admin.categories.index'))->assertForbidden();
    $this->actingAs($participant)->post(route('admin.categories.store'), validCategory())->assertForbidden();
    $this->actingAs($participant)->delete(route('admin.categories.destroy', $category))->assertForbidden();

    expect(AwardCategory::count())->toBe(1);
});

test('admins see categories with their submission counts', function () {
    $category = AwardCategory::factory()->create(['sort_order' => 1]);
    AwardCategory::factory()->create(['sort_order' => 2]);
    Submission::factory()->count(2)->for($category, 'awardCategory')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/categories/index')
            ->has('categories', 2)
            ->where('categories.0.id', $category->id)
            ->where('categories.0.submissions_count', 2)
            ->where('categories.1.submissions_count', 0));
});

test('admins can create a category', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), validCategory())
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasNoErrors();

    $category = AwardCategory::sole();

    expect($category->name)->toBe('Best Community Initiative')
        ->and($category->applicant_type)->toBe(ApplicantType::Individual)
        ->and($category->sort_order)->toBe(3);
});

test('category names must be unique and applicant types valid', function () {
    AwardCategory::factory()->create(['name' => 'Best Community Initiative']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), [...validCategory(), 'applicant_type' => 'company'])
        ->assertSessionHasErrors(['name', 'applicant_type']);

    expect(AwardCategory::count())->toBe(1);
});

test('admins can update a category while keeping its name', function () {
    $category = AwardCategory::factory()->create(['name' => 'Best Community Initiative']);

    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.categories.update', $category), [...validCategory(), 'sort_order' => 9])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasNoErrors();

    expect($category->refresh()->sort_order)->toBe(9)
        ->and($category->applicant_type)->toBe(ApplicantType::Individual);
});

test('admins can delete a category without submissions', function () {
    $category = AwardCategory::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect(AwardCategory::count())->toBe(0);
});

test('categories with submissions cannot be deleted', function () {
    $submission = Submission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.categories.destroy', $submission->award_category_id))
        ->assertRedirect(route('admin.categories.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(AwardCategory::count())->toBe(1);
});

test('admins can assign an existing assessment template to a category', function () {
    $template = AssessmentTemplate::factory()->create();
    $category = AwardCategory::factory()->create(['name' => 'Best Community Initiative']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.categories.update', $category), [...validCategory(), 'assessment_template_id' => $template->id + 1])
        ->assertSessionHasErrors('assessment_template_id');

    $this->actingAs($admin)
        ->post(route('admin.categories.update', $category), [...validCategory(), 'assessment_template_id' => $template->id])
        ->assertSessionHasNoErrors();

    expect($category->refresh()->assessment_template_id)->toBe($template->id);
});

test('admins can upload a paper template when creating a category', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), [...validCategory(), 'paper_template' => UploadedFile::fake()->create('Community Template.pptx', 100)])
        ->assertRedirect(route('admin.categories.index'))
        ->assertSessionHasNoErrors();

    $category = AwardCategory::sole();

    expect($category->paper_template_name)->toBe('Community Template.pptx');
    Storage::disk('public')->assertExists($category->paper_template_path);

    $this->get(route('admin.categories.index'))
        ->assertInertia(fn ($page) => $page
            ->where('categories.0.paper_template_name', 'Community Template.pptx')
            ->where('categories.0.paper_template_url', Storage::disk('public')->url($category->paper_template_path))
            ->missing('categories.0.paper_template_path'));
});

test('replacing a paper template deletes the previous file', function () {
    Storage::fake('public');
    $oldPath = UploadedFile::fake()->create('old.pdf', 10)->store('categories', 'public');
    $category = AwardCategory::factory()->create(['paper_template_path' => $oldPath, 'paper_template_name' => 'old.pdf']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.update', $category), [...validCategory(), 'paper_template' => UploadedFile::fake()->create('new.pdf', 10)])
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->paper_template_name)->toBe('new.pdf');
    Storage::disk('public')->assertExists($category->paper_template_path);
    Storage::disk('public')->assertMissing($oldPath);
});

test('admins can remove a paper template so the default one is used', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->create('template.pdf', 10)->store('categories', 'public');
    $category = AwardCategory::factory()->create(['paper_template_path' => $path, 'paper_template_name' => 'template.pdf']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.update', $category), [...validCategory(), 'remove_paper_template' => true])
        ->assertSessionHasNoErrors();

    $category->refresh();

    expect($category->paper_template_path)->toBeNull()
        ->and($category->paper_template_name)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('paper templates must be a PDF, Word, or PowerPoint file', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), [...validCategory(), 'paper_template' => UploadedFile::fake()->create('template.exe', 10)])
        ->assertSessionHasErrors([
            'paper_template' => 'The paper template field must be a file of type: pdf, doc, docx, pptx.',
        ]);

    expect(AwardCategory::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('deleting a category removes its paper template', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->create('template.pdf', 10)->store('categories', 'public');
    $category = AwardCategory::factory()->create(['paper_template_path' => $path]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    Storage::disk('public')->assertMissing($path);
});

test('a scored category keeps its assessment template, even for a superadmin', function () {
    $category = categoryWithRubric();
    scoreGivenBy(judgeAssignedTo($category), $category);
    $superadmin = User::factory()->superadmin()->create();
    $payload = [...validCategory(), 'name' => $category->name];

    $this->actingAs($superadmin)
        ->post(route('admin.categories.update', $category), [...$payload, 'assessment_template_id' => AssessmentTemplate::factory()->create()->id])
        ->assertSessionHasErrors('assessment_template_id');

    $this->actingAs($superadmin)
        ->post(route('admin.categories.update', $category), [...$payload, 'assessment_template_id' => $category->assessment_template_id, 'sort_order' => 7])
        ->assertSessionHasNoErrors();

    expect($category->refresh()->sort_order)->toBe(7);
});
