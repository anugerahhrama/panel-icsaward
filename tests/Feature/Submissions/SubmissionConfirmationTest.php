<?php

use App\Mail\SubmissionConfirmation;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('registering queues the submission confirmation instead of the default verification email', function () {
    Mail::fake();
    Notification::fake();
    $category = AwardCategory::factory()->create();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'phone' => '+62 812-3456-7890',
        'email' => 'test@example.com',
        'position' => 'Sustainability Manager',
        'company_name' => 'PT Example Indonesia',
        'password' => 'password',
        'password_confirmation' => 'password',
        'award_category_id' => $category->id,
        'initiative_title' => 'Solar Rooftops for Schools',
        'initiative_description' => 'Installing rooftop solar on 50 rural schools.',
        'terms_accepted' => true,
    ]);

    $submission = Submission::query()->sole();

    Mail::assertQueued(SubmissionConfirmation::class, fn (SubmissionConfirmation $mail): bool => $mail->hasTo('test@example.com')
        && $mail->submission->is($submission));
    Notification::assertNotSentTo($submission->user, VerifyEmail::class);
    expect($submission->confirmation_sent_at)->not->toBeNull();
});

test('resending the verification email resends the submission link', function () {
    Mail::fake();
    Notification::fake();
    $submission = Submission::factory()->for(User::factory()->unverified())->create();

    $this->actingAs($submission->user)->post(route('verification.send'));

    Mail::assertQueued(SubmissionConfirmation::class, fn (SubmissionConfirmation $mail): bool => $mail->submission->is($submission));
    Notification::assertNothingSent();
});

test('the confirmation email fills the placeholders from the settings template', function () {
    Setting::put('confirmation_email_subject', 'Confirmed: {{initiative_title}}');
    Setting::put('confirmation_email_body', 'Dear {{name}}, {{category}} closes on {{deadline}}. Link: {{submission_link}} Contact {{contact_email}}');
    Setting::put('paper_deadline', '2026-10-19');
    Setting::put('contact_email', 'info@icsaward.id');
    $submission = Submission::factory()
        ->for(User::factory()->state(['name' => 'Ayu <script>alert(1)</script>']))
        ->for(AwardCategory::factory()->state(['name' => 'Best Renewable Energy']))
        ->create(['initiative_title' => 'Solar Rooftops']);

    $mail = new SubmissionConfirmation($submission);

    $mail->assertHasSubject('Confirmed: Solar Rooftops');
    $mail->assertSeeInHtml('Dear Ayu &lt;script&gt;alert(1)&lt;/script&gt;', false);
    $mail->assertDontSeeInHtml('<script>alert(1)</script>', false);
    $mail->assertSeeInHtml('Best Renewable Energy closes on 19 October 2026, 23:59 WIB');
    $mail->assertSeeInHtml('href="'.route('submissions.show', $submission).'"', false);
    $mail->assertSeeInHtml('Contact info@icsaward.id');
});
