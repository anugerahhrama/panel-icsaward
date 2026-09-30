<?php

use App\Enums\SubmissionStatus;
use App\Jobs\SendVerificationDecision;
use App\Mail\VerificationDecision;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 10)->setTime(3, 0));
});

test('participants and judges cannot record a verification decision', function (string $role) {
    Queue::fake();
    $submission = Submission::factory()->paperSubmitted()->create();
    $user = $role === 'owner' ? $submission->user : User::factory()->judge()->create();

    $this->actingAs($user)
        ->put(route('admin.participants.verification.update', $submission), ['decision' => 'qualified'])
        ->assertForbidden();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::UnderReview);
    Queue::assertNothingPushed();
})->with(['owner', 'judge']);

test('admins can qualify a submission under review', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $submission = Submission::factory()->paperSubmitted()->create();

    $this->actingAs($admin)
        ->from(route('admin.participants.papers.index'))
        ->put(route('admin.participants.verification.update', $submission), [
            'decision' => 'qualified',
            'revision_note' => 'Ignored',
        ])
        ->assertRedirect(route('admin.participants.papers.index'))
        ->assertSessionHasNoErrors();

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Qualified)
        ->and($submission->revision_note)->toBeNull()
        ->and($submission->reviewed_by)->toBe($admin->id)
        ->and($submission->reviewed_at)->not->toBeNull()
        ->and($submission->notified_at)->toBeNull();
    Queue::assertPushed(SendVerificationDecision::class, fn (SendVerificationDecision $job): bool => $job->submission->is($submission));
});

test('asking for a revision stores the note and the deadline at the end of that day in WIB', function () {
    Queue::fake();
    $submission = Submission::factory()->paperSubmitted()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.participants.verification.update', $submission), [
            'decision' => 'needs_revision',
            'revision_note' => 'Please use the official template.',
            'revision_deadline' => '2026-10-22',
        ])
        ->assertSessionHasNoErrors();

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::NeedsRevision)
        ->and($submission->revision_note)->toBe('Please use the official template.')
        ->and($submission->revision_deadline->toIso8601String())->toBe('2026-10-22T16:59:59+00:00')
        ->and($submission->disqualified_reason)->toBeNull();
    Queue::assertPushed(SendVerificationDecision::class);
});

test('disqualifying a submission stores the reason', function () {
    Queue::fake();
    $submission = Submission::factory()->paperSubmitted()->create();

    $this->actingAs(User::factory()->superadmin()->create())
        ->put(route('admin.participants.verification.update', $submission), [
            'decision' => 'disqualified',
            'disqualified_reason' => 'The initiative started in 2019.',
        ])
        ->assertSessionHasNoErrors();

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::Disqualified)
        ->and($submission->disqualified_reason)->toBe('The initiative started in 2019.')
        ->and($submission->revision_deadline)->toBeNull();
});

test('the decision details are required for the chosen decision', function (array $payload, array $errors) {
    Queue::fake();
    $submission = Submission::factory()->paperSubmitted()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.participants.verification.update', $submission), $payload)
        ->assertSessionHasErrors($errors);

    expect($submission->fresh()->status)->toBe(SubmissionStatus::UnderReview);
    Queue::assertNothingPushed();
})->with([
    'unknown decision' => [['decision' => 'finalist'], ['decision']],
    'revision without details' => [['decision' => 'needs_revision'], ['revision_note', 'revision_deadline']],
    'revision deadline in the past' => [
        ['decision' => 'needs_revision', 'revision_note' => 'Fix it.', 'revision_deadline' => '2026-10-09'],
        ['revision_deadline' => 'The revision deadline cannot be in the past.'],
    ],
    'disqualification without a reason' => [['decision' => 'disqualified'], ['disqualified_reason']],
]);

test('a submission that is no longer under review cannot be decided again', function (SubmissionStatus $status) {
    Queue::fake();
    $submission = Submission::factory()->paperSubmitted()->create(['status' => $status]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.participants.verification.update', $submission), [
            'decision' => 'disqualified',
            'disqualified_reason' => 'Too late.',
        ])
        ->assertSessionHasErrors(['decision' => 'This submission has already been reviewed.']);

    expect($submission->fresh()->status)->toBe($status);
    Queue::assertNothingPushed();
})->with([SubmissionStatus::Registered, SubmissionStatus::Qualified, SubmissionStatus::NeedsRevision, SubmissionStatus::Disqualified]);

test('the decision email is sent once even when the job runs twice', function () {
    Mail::fake();
    $submission = Submission::factory()->paperSubmitted()->create(['status' => SubmissionStatus::Qualified]);

    (new SendVerificationDecision($submission))->handle();
    (new SendVerificationDecision($submission))->handle();

    Mail::assertSentCount(1);
    Mail::assertSent(VerificationDecision::class, fn (VerificationDecision $mail): bool => $mail->hasTo($submission->user->email));
    expect($submission->fresh()->notified_at)->not->toBeNull();
});

test('a failed send releases the notification flag so the retry can deliver it', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('SMTP down'));
    $submission = Submission::factory()->paperSubmitted()->create(['status' => SubmissionStatus::Qualified]);

    expect(fn () => (new SendVerificationDecision($submission))->handle())->toThrow(RuntimeException::class);
    expect($submission->fresh()->notified_at)->toBeNull();
});

test('the decision email uses the template of the decision with escaped placeholders', function () {
    Setting::put('needs_revision_email_subject', 'Revise: {{initiative_title}}');
    Setting::put('needs_revision_email_body', 'Dear {{name}}, {{revision_note}} by {{revision_deadline}}. {{dashboard_link}} {{contact_email}}');
    Setting::put('qualified_email_body', 'Qualified template');
    Setting::put('contact_email', 'info@icsaward.id');
    $submission = Submission::factory()
        ->for(User::factory()->state(['name' => 'Ayu <b>Lestari</b>']))
        ->for(AwardCategory::factory())
        ->paperSubmitted()
        ->create([
            'initiative_title' => 'Solar Rooftops',
            'status' => SubmissionStatus::NeedsRevision,
            'revision_note' => 'Use <the> template',
            'revision_deadline' => '2026-10-22 16:59:59',
        ]);

    $mail = new VerificationDecision($submission);

    $mail->assertHasSubject('Revise: Solar Rooftops');
    $mail->assertSeeInHtml('Dear Ayu &lt;b&gt;Lestari&lt;/b&gt;', false);
    $mail->assertSeeInHtml('Use &lt;the&gt; template', false);
    $mail->assertSeeInHtml('22 October 2026, 23:59 WIB');
    $mail->assertSeeInHtml(route('dashboard'));
    $mail->assertSeeInHtml('info@icsaward.id');
    $mail->assertDontSeeInHtml('Qualified template');
});

test('the paper submissions table exposes the verification details', function () {
    $admin = User::factory()->admin()->create(['name' => 'Committee Admin']);
    Submission::factory()->paperSubmitted()->create([
        'status' => SubmissionStatus::Disqualified,
        'disqualified_reason' => 'Out of scope.',
        'reviewed_at' => now(),
        'reviewed_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.participants.papers.index'))
        ->assertInertia(fn ($page) => $page
            ->where('submissions.data.0.verification.disqualified_reason', 'Out of scope.')
            ->where('submissions.data.0.verification.reviewer', 'Committee Admin')
            ->where('submissions.data.0.verification.notified_at', null));
});
