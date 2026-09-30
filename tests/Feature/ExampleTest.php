<?php

use App\Models\User;

test('home redirects guests to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('home sends signed-in users to their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->followingRedirects()
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard'));
});
