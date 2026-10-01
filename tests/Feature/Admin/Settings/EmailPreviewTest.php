<?php

use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;

test('judges cannot preview email templates', function () {
    $this->actingAs(User::factory()->judge()->create())
        ->postJson(route('admin.settings.email.preview'), [
            'template' => 'confirmation',
            'subject' => 'Subject',
            'body' => 'Body',
        ])
        ->assertForbidden();
});

test('admins preview the unsaved template rendered with sample data', function (string $template, string $placeholder, string $sampleValue) {
    Setting::put("{$template}_email_subject", 'Stored subject');
    Setting::put("{$template}_email_body", 'Stored body');
    Setting::put('timeline_awarding_night', '28 November 2026, Jakarta');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.settings.email.preview'), [
            'template' => $template,
            'subject' => 'Preview for {{name}}',
            'body' => "Unsaved draft about {{initiative_title}}.\n\nValue: {$placeholder}\n\nContact {{contact_email}}",
            'contact_email' => 'draft@example.com',
        ])
        ->assertOk()
        ->assertJsonPath('subject', 'Preview for Jane Doe');

    expect($response->json('html'))
        ->toContain('Unsaved draft about Community Mangrove Restoration Program.')
        ->toContain($sampleValue)
        ->toContain('Contact draft@example.com')
        ->not->toContain('Stored body')
        ->and(Setting::get("{$template}_email_body"))->toBe('Stored body')
        ->and(Submission::count())->toBe(0)
        ->and(User::count())->toBe(1);
})->with([
    'confirmation' => ['confirmation', '{{submission_link}}', '/submissions/'],
    'qualified' => ['qualified', '{{dashboard_link}}', '/dashboard'],
    'needs revision' => ['needs_revision', '{{revision_note}}', 'Please add the measurable impact'],
    'disqualified' => ['disqualified', '{{disqualified_reason}}', 'The initiative started after the eligibility period.'],
    'finalist announcement' => ['finalist_announcement', '{{pitching_schedule}}', 'Jakarta Convention Center'],
    'awarding invitation' => ['awarding_invitation', '{{awarding_night}}', '28 November 2026, Jakarta'],
    'winner announcement' => ['winner_announcement', '{{award}}', 'Gold'],
]);

test('the preview falls back to the stored contact email when the field is empty', function () {
    Setting::put('contact_email', 'info@icsaward.id');

    $response = $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.settings.email.preview'), [
            'template' => 'qualified',
            'subject' => 'Qualified',
            'body' => 'Contact {{contact_email}}',
            'contact_email' => '',
        ])
        ->assertOk();

    expect($response->json('html'))->toContain('Contact info@icsaward.id');
});

test('the preview requires a known template with a subject and body', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->postJson(route('admin.settings.email.preview'), [
            'template' => 'unknown',
            'subject' => '',
            'body' => '',
            'contact_email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'template' => 'The selected template is invalid.',
            'subject' => 'The subject field is required.',
            'body' => 'The body field is required.',
            'contact_email' => 'The contact email field must be a valid email address.',
        ]);
});
