<?php

use App\Models\Setting;
use App\Models\User;

test('judges cannot update email settings', function () {
    $this->actingAs(User::factory()->judge()->create())
        ->put(route('admin.settings.email.update'), [
            'confirmation_email_subject' => 'Subject',
            'confirmation_email_body' => 'Body',
            'contact_email' => 'committee@example.com',
        ])
        ->assertForbidden();
});

test('admins can update the email templates and contact email', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.email.update'), [
            'confirmation_email_subject' => 'Welcome {{name}}',
            'confirmation_email_body' => "Dear {{name}},\n\n{{submission_link}}",
            'qualified_email_subject' => 'Qualified',
            'qualified_email_body' => 'Congratulations {{name}}',
            'needs_revision_email_subject' => 'Needs revision',
            'needs_revision_email_body' => '{{revision_note}} by {{revision_deadline}}',
            'disqualified_email_subject' => 'Disqualified',
            'disqualified_email_body' => '{{disqualified_reason}}',
            'contact_email' => 'committee@example.com',
        ])
        ->assertRedirect(route('admin.settings.email.edit'))
        ->assertSessionHasNoErrors();

    expect(Setting::get('confirmation_email_subject'))->toBe('Welcome {{name}}')
        ->and(Setting::get('confirmation_email_body'))->toBe("Dear {{name}},\n\n{{submission_link}}")
        ->and(Setting::get('needs_revision_email_body'))->toBe('{{revision_note}} by {{revision_deadline}}')
        ->and(Setting::get('contact_email'))->toBe('committee@example.com');
});

test('the email settings are required and the contact must be an email address', function () {
    Setting::put('contact_email', 'info@icsaward.id');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.settings.email.update'), [
            'confirmation_email_subject' => '',
            'confirmation_email_body' => '',
            'contact_email' => 'not-an-email',
        ])
        ->assertSessionHasErrors([
            'confirmation_email_subject' => 'The subject field is required.',
            'confirmation_email_body' => 'The body field is required.',
            'disqualified_email_subject' => 'The subject field is required.',
            'contact_email' => 'The contact email field must be a valid email address.',
        ]);

    expect(Setting::get('contact_email'))->toBe('info@icsaward.id');
});
