<?php

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});

test('login screen shares the registration period from settings', function () {
    Setting::put('registration_opens_at', '2026-09-28');
    Setting::put('registration_deadline', '2026-10-19');
    $this->travelTo(CarbonImmutable::parse('2026-10-19 23:59:59', Setting::EVENT_TIMEZONE));

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('registrationPeriod.opensAt', '2026-09-28')
            ->where('registrationPeriod.closesAt', '2026-10-19')
            ->where('registrationPeriod.isOpen', true));
});

test('login screen shares that registration is not open', function (string $key, string $value) {
    Setting::put('registration_opens_at', '2026-09-28');
    Setting::put('registration_deadline', '2026-10-19');
    Setting::put($key, $value);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00', Setting::EVENT_TIMEZONE));

    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('registrationPeriod.isOpen', false));
})->with([
    'closed by admin' => ['is_registration_open', '0'],
    'not open yet' => ['registration_opens_at', '2026-10-02'],
    'past the deadline' => ['registration_deadline', '2026-09-30'],
]);
