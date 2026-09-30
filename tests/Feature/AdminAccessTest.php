<?php

use App\Enums\UserRole;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('non-admin users are forbidden from the admin panel', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
})->with([
    'participant' => UserRole::Participant,
    'judge' => UserRole::Judge,
]);

test('superadmins and admins can visit the admin dashboard', function (UserRole $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route('admin.dashboard'))
        ->assertOk();
})->with([
    'superadmin' => UserRole::Superadmin,
    'admin' => UserRole::Admin,
]);

test('the admin panel is served under the configured prefix, not /admin', function () {
    config(['admin.prefix' => 'panel-test']);

    expect(route('admin.dashboard', absolute: false))->not->toStartWith('/admin');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/dashboard')
        ->assertNotFound();
});
