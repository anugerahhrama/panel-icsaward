<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureLandingApiToken;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin-only management of the bearer token the Landing repo uses to read the Landing API.
 */
class LandingApiSettingsController extends Controller
{
    /**
     * Show the token status and the endpoints; the token itself is only sent on an explicit reveal.
     */
    public function edit(): Response
    {
        $token = Setting::getEncrypted(EnsureLandingApiToken::SETTING_KEY);

        return Inertia::render('admin/settings/landing-api', [
            'tokenHint' => $token === null ? null : Str::substr($token, -4),
            'token' => Inertia::optional(fn (): ?string => Setting::getEncrypted(EnsureLandingApiToken::SETTING_KEY)),
            'endpoints' => [
                ['label' => 'Categories', 'url' => route('api.v1.landing.categories')],
                ['label' => 'Judges', 'url' => route('api.v1.landing.judges')],
                ['label' => 'Stats', 'url' => route('api.v1.landing.stats')],
            ],
        ]);
    }

    /**
     * Generate a new token, invalidating the previous one.
     */
    public function store(): RedirectResponse
    {
        Setting::putEncrypted(EnsureLandingApiToken::SETTING_KEY, Str::random(64));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'New Landing API token generated. Update ASSESSMENT_API_TOKEN on the landing site.']);

        return to_route('admin.settings.landing-api.edit');
    }

    /**
     * Revoke the token, which disables the Landing API.
     */
    public function destroy(): RedirectResponse
    {
        Setting::putEncrypted(EnsureLandingApiToken::SETTING_KEY, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Landing API token revoked.']);

        return to_route('admin.settings.landing-api.edit');
    }
}
