<?php

use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\User;

test('each sign up step accepts valid data', function (string $step, array $payload) {
    $this->postJson(route('register.validate', $step), $payload)->assertNoContent();
})->with([
    'account' => ['account', [
        'name' => 'Test User',
        'phone' => '+62 812-3456-7890',
        'email' => 'test@example.com',
        'position' => 'Sustainability Manager',
        'company_name' => 'PT Example Indonesia',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]],
    'terms' => ['terms', ['terms_accepted' => true]],
]);

test('the initiative step accepts an existing category', function () {
    $category = AwardCategory::factory()->create();

    $this->postJson(route('register.validate', 'initiative'), [
        'award_category_id' => $category->id,
        'initiative_title' => 'Solar Rooftops for Schools',
        'initiative_description' => 'Installing rooftop solar on 50 rural schools.',
    ])->assertNoContent();
});

test('each sign up step only reports errors for its own fields', function (string $step, array $expectedErrors) {
    $this->postJson(route('register.validate', $step), [])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors($expectedErrors);
})->with([
    'account' => ['account', ['name', 'phone', 'email', 'position', 'company_name', 'password']],
    'initiative' => ['initiative', ['award_category_id', 'initiative_title', 'initiative_description']],
    'terms' => ['terms', ['terms_accepted']],
]);

test('the account step rejects an email that is already registered', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson(route('register.validate', 'account'), ['email' => 'taken@example.com'])
        ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
});

test('the account step rejects an invalid phone number', function () {
    $this->postJson(route('register.validate', 'account'), ['phone' => 'call me'])
        ->assertJsonValidationErrors(['phone' => 'Please enter a valid phone number.']);
});

test('an unknown step is not found', function () {
    $this->postJson('/register/validate/payment', [])->assertNotFound();
});

test('authenticated users cannot use the sign up step validation', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('register.validate', 'terms'), ['terms_accepted' => true])
        ->assertRedirect();
});

test('every sign up step is rejected while registration is closed', function () {
    Setting::put('is_registration_open', '0');

    $this->postJson(route('register.validate', 'terms'), ['terms_accepted' => true])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors(['registration' => 'Registration is closed.']);
});
