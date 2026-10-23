<?php

use App\Enums\UserRole;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+62 812-3456-7890',
            'position' => 'Sustainability Manager',
            'company_name' => 'PT Example Indonesia',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->only(['phone', 'position', 'company_name']))->toBe([
        'phone' => '+62 812-3456-7890',
        'position' => 'Sustainability Manager',
        'company_name' => 'PT Example Indonesia',
    ]);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            ...$user->only(['phone', 'position', 'company_name']),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('participants must keep valid contact details', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => 'call me',
        ])
        ->assertSessionHasErrors([
            'phone' => 'Please enter a valid phone number.',
            'position',
            'company_name',
        ]);
});

test('staff update their name and email without contact details', function (string $role) {
    $user = User::factory()->{$role}()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Committee Member',
            'email' => $user->email,
            'phone' => '+62 811-0000-0000',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())
        ->name->toBe('Committee Member')
        ->phone->toBe($user->phone);
})->with(['admin', 'judge']);

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    $this->assertModelMissing($user);
});

test('admins cannot delete their own account from profile settings', function (string $role) {
    $user = User::factory()->create(['role' => UserRole::from($role)]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    $this->assertNotSoftDeleted($user);
})->with(['admin', 'superadmin']);

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
