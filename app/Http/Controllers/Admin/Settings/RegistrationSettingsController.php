<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Enums\TimelineStage;
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
     * The settings this page reads and writes, besides the registration toggle.
     *
     * @return list<string>
     */
    private function keys(): array
    {
        return [
            'registration_opens_at',
            'registration_deadline',
            'paper_deadline',
            'max_registrations_per_user',
            ...TimelineStage::settingKeys(),
        ];
    }

    /**
     * Show the registration & deadlines settings.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/registration', [
            'settings' => [
                ...Setting::many($this->keys()),
                'is_registration_open' => Setting::get('is_registration_open', '1') === '1',
            ],
            'timelineStages' => TimelineStage::forSettingsForm(),
        ]);
    }

    /**
     * Update the registration & deadlines settings.
     */
    public function update(UpdateRegistrationSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            foreach ($this->keys() as $key) {
                $value = $validated[$key] ?? null;

                Setting::put($key, $value === null ? null : (string) $value);
            }

            Setting::put('is_registration_open', $request->boolean('is_registration_open') ? '1' : '0');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Registration settings saved.']);

        return to_route('admin.settings.registration.edit');
    }
}
