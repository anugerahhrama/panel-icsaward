<?php

use App\Http\Middleware\EnsureLandingApiToken;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins cannot manage the landing api token', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.settings.landing-api.store'))
        ->assertForbidden();

    expect(Setting::getEncrypted(EnsureLandingApiToken::SETTING_KEY))->toBeNull();
});

test('superadmins can generate a token, stored encrypted', function () {
    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.settings.landing-api.store'))
        ->assertRedirect(route('admin.settings.landing-api.edit'));

    $token = Setting::getEncrypted(EnsureLandingApiToken::SETTING_KEY);
    expect($token)->toHaveLength(64)
        ->and(Setting::get(EnsureLandingApiToken::SETTING_KEY))->not->toContain($token);
});

test('regenerating replaces the previous token', function () {
    Setting::putEncrypted(EnsureLandingApiToken::SETTING_KEY, 'old-token');

    $this->actingAs(User::factory()->superadmin()->create())
        ->post(route('admin.settings.landing-api.store'));

    $this->getJson(route('api.v1.landing.categories'), ['Authorization' => 'Bearer old-token'])
        ->assertUnauthorized();
});

test('the token is only sent when revealed', function () {
    Setting::putEncrypted(EnsureLandingApiToken::SETTING_KEY, 'secret-token-abcd');

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('admin.settings.landing-api.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/landing-api')
            ->where('tokenHint', 'abcd')
            ->has('endpoints', 3)
            ->missing('token')
            ->reloadOnly('token', fn (Assert $reload) => $reload->where('token', 'secret-token-abcd')));
});

test('superadmins can revoke the token', function () {
    Setting::putEncrypted(EnsureLandingApiToken::SETTING_KEY, 'secret-token-abcd');

    $this->actingAs(User::factory()->superadmin()->create())
        ->delete(route('admin.settings.landing-api.destroy'))
        ->assertRedirect(route('admin.settings.landing-api.edit'));

    expect(Setting::getEncrypted(EnsureLandingApiToken::SETTING_KEY))->toBeNull();
});
