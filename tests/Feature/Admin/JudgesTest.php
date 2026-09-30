<?php

use App\Enums\UserRole;
use App\Models\AwardCategory;
use App\Models\Judge;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validJudge(array $overrides = []): array
{
    return [
        'name' => 'Dr. Jane Doe',
        'position' => 'Director',
        'institution' => 'IBCSD',
        'bio' => 'Sustainability expert.',
        'show_on_landing' => true,
        'landing_category_id' => null,
        'sort_order' => 1,
        'has_account' => false,
        'assignments' => [],
        ...$overrides,
    ];
}

beforeEach(function () {
    Storage::fake('public');
});

test('participants and judges cannot manage judges', function (string $role) {
    $judge = Judge::factory()->create();
    $user = User::factory()->create(['role' => UserRole::from($role)]);

    $this->actingAs($user)->get(route('admin.judges.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.judges.store'), validJudge())->assertForbidden();
    $this->actingAs($user)->delete(route('admin.judges.destroy', $judge))->assertForbidden();

    expect(Judge::count())->toBe(1);
})->with(['participant', 'judge']);

test('admins see judges with their account and assignment counts', function () {
    $judge = Judge::factory()->withAccount()->create();
    $categories = AwardCategory::factory()->count(2)->create();
    $judge->categories()->attach([$categories[0]->id => ['is_recused' => false], $categories[1]->id => ['is_recused' => true]]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.judges.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/judges/index')
            ->has('judges', 1)
            ->where('judges.0.account_email', $judge->user->email)
            ->where('judges.0.categories_count', 2)
            ->where('judges.0.recused_categories_count', 1));
});

test('admins can create a judge profile without an account', function () {
    $categories = AwardCategory::factory()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.judges.store'), validJudge([
            'photo' => UploadedFile::fake()->image('jane.jpg'),
            'landing_category_id' => $categories[0]->id,
            'assignments' => [
                ['award_category_id' => $categories[0]->id, 'is_recused' => false],
                ['award_category_id' => $categories[1]->id, 'is_recused' => true],
            ],
        ]))
        ->assertRedirect(route('admin.judges.index'))
        ->assertSessionHasNoErrors();

    $judge = Judge::sole();

    Storage::disk('public')->assertExists($judge->photo_path);
    expect($judge->user_id)->toBeNull()
        ->and($judge->show_on_landing)->toBeTrue()
        ->and($judge->landing_category_id)->toBe($categories[0]->id)
        ->and($judge->categories()->pluck('is_recused', 'award_category_id')->map(fn ($value) => (bool) $value)->all())
        ->toBe([$categories[0]->id => false, $categories[1]->id => true]);
});

test('admins can create a judge with a verified login account', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.judges.store'), validJudge([
            'has_account' => true,
            'email' => 'jane@example.com',
            'password' => 'Secret-Pass-123',
            'password_confirmation' => 'Secret-Pass-123',
        ]))
        ->assertSessionHasNoErrors();

    $judge = Judge::sole();
    $user = $judge->user;

    expect($user->role)->toBe(UserRole::Judge)
        ->and($user->name)->toBe('Dr. Jane Doe')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Secret-Pass-123', $user->password))->toBeTrue()
        ->and($judge->account_password)->toBe('Secret-Pass-123')
        ->and(DB::table('judges')->value('account_password'))->not->toBe('Secret-Pass-123');
});

test('a new account needs a unique email and a confirmed password', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.judges.store'), validJudge([
            'has_account' => true,
            'email' => 'taken@example.com',
            'password' => 'Secret-Pass-123',
            'password_confirmation' => 'Other-Pass-123',
        ]))
        ->assertSessionHasErrors(['email', 'password']);

    expect(Judge::count())->toBe(0);
});

test('updating a judge keeps the password when left blank and syncs assignments', function () {
    $judge = Judge::factory()->withAccount('Old-Pass-123')->create();
    [$kept, $dropped, $added] = AwardCategory::factory()->count(3)->create();
    $judge->categories()->attach([$kept->id => ['is_recused' => false], $dropped->id => ['is_recused' => false]]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.judges.update', $judge), validJudge([
            'has_account' => true,
            'email' => 'new@example.com',
            'password' => '',
            'assignments' => [
                ['award_category_id' => $kept->id, 'is_recused' => true],
                ['award_category_id' => $added->id, 'is_recused' => false],
            ],
        ]))
        ->assertRedirect(route('admin.judges.index'))
        ->assertSessionHasNoErrors();

    $judge->refresh();

    expect($judge->user->email)->toBe('new@example.com')
        ->and(Hash::check('Old-Pass-123', $judge->user->password))->toBeTrue()
        ->and($judge->account_password)->toBe('Old-Pass-123')
        ->and($judge->categories()->pluck('is_recused', 'award_category_id')->map(fn ($value) => (bool) $value)->all())
        ->toBe([$kept->id => true, $added->id => false]);
});

test('replacing a photo deletes the previous file', function () {
    $judge = Judge::factory()->create(['photo_path' => UploadedFile::fake()->image('old.jpg')->store('judges', 'public')]);
    $oldPath = $judge->photo_path;

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.judges.update', $judge), validJudge(['photo' => UploadedFile::fake()->image('new.jpg')]))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($judge->refresh()->photo_path);
});

test('an existing login account cannot be removed and categories cannot repeat', function () {
    $judge = Judge::factory()->withAccount()->create();
    $category = AwardCategory::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.judges.update', $judge), validJudge([
            'has_account' => false,
            'assignments' => [
                ['award_category_id' => $category->id, 'is_recused' => false],
                ['award_category_id' => $category->id, 'is_recused' => true],
            ],
        ]))
        ->assertSessionHasErrors(['has_account', 'assignments.0.award_category_id']);
});

test('only superadmins can reveal the password the admin set', function () {
    $judge = Judge::factory()->withAccount('Secret-Pass-123')->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('admin.judges.edit', $judge))
        ->assertInertia(fn ($page) => $page
            ->missing('accountPassword')
            ->where('judge.has_account_password', true)
            ->reloadOnly('accountPassword', fn ($reload) => $reload->where('accountPassword', 'Secret-Pass-123')));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.judges.edit', $judge))
        ->assertInertia(fn ($page) => $page
            ->where('judge.has_account_password', false)
            ->reloadOnly('accountPassword', fn ($reload) => $reload->where('accountPassword', null)));
});

test('the stored password is discarded once the judge changes it', function () {
    $judge = Judge::factory()->withAccount('Secret-Pass-123')->create();
    $judge->user->update(['password' => 'Changed-Pass-456']);

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('admin.judges.edit', $judge))
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('accountPassword', fn ($reload) => $reload->where('accountPassword', null)));

    expect($judge->refresh()->account_password)->toBeNull();
});

test('judges assigned to a category cannot be deleted', function () {
    $judge = Judge::factory()->create();
    $judge->categories()->attach(AwardCategory::factory()->create());

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.judges.destroy', $judge))
        ->assertRedirect(route('admin.judges.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Judge::count())->toBe(1);
});

test('deleting an unassigned judge removes its photo and login account', function () {
    $judge = Judge::factory()->withAccount()->create(['photo_path' => UploadedFile::fake()->image('jane.jpg')->store('judges', 'public')]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.judges.destroy', $judge))
        ->assertRedirect(route('admin.judges.index'))
        ->assertInertiaFlash('toast.type', 'success');

    Storage::disk('public')->assertMissing($judge->photo_path);
    expect(Judge::count())->toBe(0)
        ->and(User::withTrashed()->find($judge->user_id))->toBeNull();
});

test('a judge who has scored a category can be recused but not unassigned', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    scoreGivenBy($judge, $category);
    $superadmin = User::factory()->superadmin()->create();
    $payload = validJudge(['has_account' => true, 'email' => $judge->user->email]);

    $this->actingAs($superadmin)
        ->post(route('admin.judges.update', $judge), $payload)
        ->assertSessionHasErrors('assignments');

    $this->actingAs($superadmin)
        ->post(route('admin.judges.update', $judge), [...$payload, 'assignments' => [['award_category_id' => $category->id, 'is_recused' => true]]])
        ->assertSessionHasNoErrors();

    expect((bool) $judge->categories()->sole()->getRelationValue('pivot')->getAttribute('is_recused'))->toBeTrue()
        ->and($judge->scores()->count())->toBe(1);
});

test('judges who have given scores cannot be deleted', function () {
    $category = categoryWithRubric();
    $judge = judgeAssignedTo($category);
    scoreGivenBy($judge, $category);
    $judge->categories()->detach();

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.judges.destroy', $judge))
        ->assertRedirect(route('admin.judges.index'))
        ->assertInertiaFlash('toast.type', 'error');

    expect(Judge::count())->toBe(1);
});
