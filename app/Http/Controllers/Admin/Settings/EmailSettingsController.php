<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateEmailSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsController extends Controller
{
    /**
     * @var list<string>
     */
    private const array KEYS = [
        'confirmation_email_subject',
        'confirmation_email_body',
        'qualified_email_subject',
        'qualified_email_body',
        'needs_revision_email_subject',
        'needs_revision_email_body',
        'disqualified_email_subject',
        'disqualified_email_body',
        'contact_email',
    ];

    /**
     * Show the email template settings.
     */
    public function edit(): Response
    {
        return Inertia::render('admin/settings/email', [
            'settings' => Setting::many(self::KEYS),
        ]);
    }

    /**
     * Update the email template settings.
     */
    public function update(UpdateEmailSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            foreach (self::KEYS as $key) {
                Setting::put($key, $validated[$key]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email settings saved.']);

        return to_route('admin.settings.email.edit');
    }
}
