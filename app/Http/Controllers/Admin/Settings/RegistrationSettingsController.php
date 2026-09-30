<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateRegistrationSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationSettingsController extends Controller
{
    /**
     * @var list<string>
     */
    private const array KEYS = [
        'registration_opens_at',
        'registration_deadline',
        'paper_deadline',
        'max_registrations_per_user',
        ...UpdateRegistrationSettingsRequest::TIMELINE_KEYS,
    ];

    /**
     * Show the registration & deadlines settings.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/registration', [
            'settings' => [
                ...Setting::many(self::KEYS),
                'is_registration_open' => Setting::get('is_registration_open', '1') === '1',
            ],
        ]);
    }

    /**
     * Update the registration & deadlines settings.
     */
    public function update(UpdateRegistrationSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            foreach (self::KEYS as $key) {
                $value = $validated[$key] ?? null;

                Setting::put($key, $value === null ? null : (string) $value);
            }

            Setting::put('is_registration_open', $request->boolean('is_registration_open') ? '1' : '0');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registration settings saved.']);

        return to_route('admin.settings.registration.edit');
    }
}
