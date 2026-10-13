<?php

use App\Models\Setting;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

function googleUser(string $email, bool $emailVerified = true): GoogleUser
{
    return (new GoogleUser)
        ->setRaw(['email' => $email, 'email_verified' => $emailVerified])
        ->map(['id' => 'google-id', 'name' => 'Google User', 'email' => $email]);
}

test('the redirect route sends guests to google', function () {
    Socialite::fake('google');

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

test('participants can log in with the google account that owns their email', function () {
    $participant = User::factory()->create();
    Socialite::fake('google', googleUser($participant->email));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($participant);
});

test('google login marks an unverified participant as verified', function () {
    $participant = User::factory()->unverified()->create();
    Socialite::fake('google', googleUser($participant->email));

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($participant);
    expect($participant->fresh()->email_verified_at)->not->toBeNull();
});

test('google login skips the two factor challenge', function () {
    $participant = User::factory()->withTwoFactor()->create();
    Socialite::fake('google', googleUser($participant->email));

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($participant);
});

test('google login is refused', function (GoogleUser|Closure $googleUser) {
    Socialite::fake('google', $googleUser);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
})->with([
    'for an admin' => fn () => googleUser(User::factory()->admin()->create()->email),
    'for a superadmin' => fn () => googleUser(User::factory()->superadmin()->create()->email),
    'for a judge' => fn () => googleUser(User::factory()->judge()->create()->email),
    'for a deleted admin' => fn () => googleUser(User::factory()->admin()->trashed()->create()->email),
    'when google has not verified the email' => fn () => googleUser(User::factory()->create()->email, emailVerified: false),
    'when the oauth state is invalid' => fn () => fn () => throw new InvalidStateException,
]);

test('google login is refused when the participant cancels on google', function () {
    Socialite::fake('google', googleUser(User::factory()->create()->email));

    $this->get(route('auth.google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
});

test('authenticated users cannot reach the google callback', function () {
    $participant = User::factory()->create();
    Socialite::fake('google', googleUser($participant->email));

    $this->actingAs($participant)
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));
});

test('an unregistered google email continues to the sign up wizard', function () {
    Socialite::fake('google', googleUser('new@example.com'));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('register'))
        ->assertSessionHas('google_signup', ['name' => 'Google User', 'email' => 'new@example.com']);

    $this->assertGuest();
});

test('an unregistered google email is refused while registration is closed', function () {
    Setting::put('is_registration_open', '0');
    Socialite::fake('google', googleUser('new@example.com'));

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['google' => 'Registration is closed.'])
        ->assertSessionMissing('google_signup');
});

test('participants can drop the google profile to sign up with another email', function () {
    $this->withSession(['google_signup' => ['name' => 'Google User', 'email' => 'new@example.com']])
        ->delete(route('auth.google.signup.cancel'))
        ->assertRedirect(route('register'))
        ->assertSessionMissing('google_signup');
});
