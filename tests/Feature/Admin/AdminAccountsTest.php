<?php

use App\Actions\Accounts\DeleteAdminAccount;
use App\Actions\Accounts\SaveAdminAccount;
use App\Enums\UserRole;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @return array<string, mixed>
 */
function validAdminAccount(array $overrides = []): array
{
    return [
        'name' => 'Rina Panitia',
        'email' => 'rina@example.com',
        'position' => 'Marketing',
        'phone' => '081200000000',
        'role' => 'admin',
        'password' => 'Str0ng!Password',
        'password_confirmation' => 'Str0ng!Password',
        ...$overrides,
    ];
}

test('only superadmins can manage admin accounts', function (string $role) {
    $account = User::factory()->admin()->create();
    $user = User::factory()->create(['role' => UserRole::from($role)]);

    $this->actingAs($user)->get(route('admin.accounts.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.accounts.store'), validAdminAccount())->assertForbidden();
    $this->actingAs($user)->put(route('admin.accounts.update', $account), validAdminAccount())->assertForbidden();
    $this->actingAs($user)->delete(route('admin.accounts.destroy', $account))->assertForbidden();

    $this->assertNotSoftDeleted($account);
    expect(User::where('email', 'rina@example.com')->exists())->toBeFalse();
})->with(['admin', 'judge', 'participant']);

test('superadmins see active and deleted admin accounts only', function () {
    $superadmin = User::factory()->superadmin()->create();
    $admin = User::factory()->admin()->create();
    $deleted = User::factory()->admin()->create(['deleted_at' => now()]);
    User::factory()->judge()->create();
    User::factory()->create();

    $this->actingAs($superadmin)
        ->get(route('admin.accounts.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/accounts/index')
            ->has('accounts', 2)
            ->where('accounts', fn ($accounts) => collect($accounts)->pluck('id')->sort()->values()->all() === collect([$superadmin->id, $admin->id])->sort()->values()->all())
            ->has('deletedAccounts', 1)
            ->where('deletedAccounts.0.id', $deleted->id));
});

test('superadmins create verified admin accounts that can sign in', function (string $role) {
    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.accounts.store'), validAdminAccount(['role' => $role]))
        ->assertRedirect(route('admin.accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $account = User::where('email', 'rina@example.com')->firstOrFail();

    expect($account->role)->toBe(UserRole::from($role))
        ->and($account->email_verified_at)->not->toBeNull()
        ->and($account->position)->toBe('Marketing')
        ->and(Hash::check('Str0ng!Password', $account->password))->toBeTrue();
})->with(['admin', 'superadmin']);

test('accounts can only be given an admin role', function (string $role) {
    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.accounts.store'), validAdminAccount(['role' => $role]))
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'rina@example.com')->exists())->toBeFalse();
})->with(['judge', 'participant']);

test('a password is required for a new account', function () {
    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.accounts.store'), validAdminAccount(['password' => '', 'password_confirmation' => '']))
        ->assertSessionHasErrors('password');
});

test('an email used by another account is rejected', function () {
    User::factory()->create(['email' => 'rina@example.com']);

    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.accounts.store'), validAdminAccount())
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

test('an email of a deleted account points to restore instead', function () {
    User::factory()->admin()->create(['email' => 'rina@example.com', 'deleted_at' => now()]);

    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.accounts.store'), validAdminAccount())
        ->assertSessionHasErrors(['email' => 'This email belongs to a deleted account. Restore it from the Deleted tab instead.']);

    expect(User::withTrashed()->where('email', 'rina@example.com')->count())->toBe(1);
});

test('updating an account resets the password only when a new one is given', function () {
    $account = User::factory()->admin()->create();
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->put(route('admin.accounts.update', $account), validAdminAccount(['password' => '', 'password_confirmation' => '']))
        ->assertRedirect(route('admin.accounts.index'));

    expect($account->refresh()->name)->toBe('Rina Panitia')
        ->and(Hash::check('password', $account->password))->toBeTrue();

    $this->actingAs($superadmin)
        ->put(route('admin.accounts.update', $account), validAdminAccount(['password' => 'N3w!Password99', 'password_confirmation' => 'N3w!Password99']))
        ->assertRedirect(route('admin.accounts.index'));

    expect(Hash::check('N3w!Password99', $account->refresh()->password))->toBeTrue();
});

test('superadmins cannot change their own role', function () {
    $superadmin = User::factory()->superadmin()->create();
    User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->put(route('admin.accounts.update', $superadmin), validAdminAccount(['role' => 'admin', 'email' => $superadmin->email, 'password' => '', 'password_confirmation' => '']))
        ->assertSessionHasErrors(['role' => 'You cannot change your own role.']);

    expect($superadmin->refresh()->role)->toBe(UserRole::Superadmin);
});

test('superadmins can demote another superadmin while one remains', function () {
    $other = User::factory()->superadmin()->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->put(route('admin.accounts.update', $other), validAdminAccount(['role' => 'admin', 'password' => '', 'password_confirmation' => '']))
        ->assertSessionHasNoErrors();

    expect($other->refresh()->role)->toBe(UserRole::Admin);
});

test('judge and participant accounts cannot be managed from admin accounts', function (string $role) {
    $account = User::factory()->create(['role' => UserRole::from($role)]);
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)->put(route('admin.accounts.update', $account), validAdminAccount())->assertNotFound();
    $this->actingAs($superadmin)->delete(route('admin.accounts.destroy', $account))->assertNotFound();

    expect($account->refresh()->role)->toBe(UserRole::from($role));
    $this->assertNotSoftDeleted($account);
})->with(['judge', 'participant']);

test('deleting an account soft deletes it and keeps its review history', function () {
    $account = User::factory()->admin()->create();
    $submission = Submission::factory()->create(['reviewed_by' => $account->id]);

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.accounts.destroy', $account))
        ->assertRedirect(route('admin.accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->assertSoftDeleted($account);
    expect($submission->refresh()->reviewer?->is($account))->toBeTrue();
});

test('deleted accounts cannot sign in or keep their session', function () {
    $account = User::factory()->admin()->create(['deleted_at' => now()]);

    $this->post(route('login.store'), ['email' => $account->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->withSession([Auth::guard('web')->getName() => $account->id])
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('superadmins cannot delete their own account', function () {
    $superadmin = User::factory()->superadmin()->create();
    User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->delete(route('admin.accounts.destroy', $superadmin))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'You cannot delete your own account.']);

    $this->assertNotSoftDeleted($superadmin);
});

/**
 * Over HTTP the only superadmin left is always the acting user, which the self guard refuses
 * first; the last-superadmin guard protects concurrent requests, so it is tested on the actions.
 */
test('the last active superadmin cannot be removed', function () {
    $lastSuperadmin = User::factory()->superadmin()->create();
    User::factory()->superadmin()->create(['deleted_at' => now()]);

    expect(fn () => app(DeleteAdminAccount::class)->handle(User::factory()->admin()->create(), $lastSuperadmin))
        ->toThrow(ValidationException::class, 'At least one active superadmin account must remain.')
        ->and(fn () => app(SaveAdminAccount::class)->handle(User::factory()->admin()->create(), $lastSuperadmin, [
            'name' => $lastSuperadmin->name,
            'email' => $lastSuperadmin->email,
            'position' => null,
            'phone' => null,
            'role' => UserRole::Admin,
            'password' => null,
        ]))
        ->toThrow(ValidationException::class, 'At least one active superadmin account must remain.');

    $this->assertNotSoftDeleted($lastSuperadmin);
    expect($lastSuperadmin->refresh()->role)->toBe(UserRole::Superadmin);
});

test('superadmins restore deleted accounts', function () {
    $account = User::factory()->admin()->create(['deleted_at' => now()]);

    $this->actingAs(User::factory()->superadmin()->create())
        ->patch(route('admin.accounts.restore', $account))
        ->assertRedirect(route('admin.accounts.index'))
        ->assertInertiaFlash('toast.type', 'success');

    $this->assertNotSoftDeleted($account);

    Auth::logout();
    $this->post(route('login.store'), ['email' => $account->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($account);
});

test('admins cannot restore deleted accounts', function () {
    $account = User::factory()->admin()->create(['deleted_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('admin.accounts.restore', $account))
        ->assertForbidden();

    $this->assertSoftDeleted($account);
});
