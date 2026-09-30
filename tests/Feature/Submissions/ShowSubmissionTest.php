<?php

use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to login', function () {
    $submission = Submission::factory()->create();

    $this->get(route('submissions.show', $submission))
        ->assertRedirect(route('login'));
});

test('another participant cannot open the submission page', function () {
    $submission = Submission::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('submissions.show', $submission))
        ->assertForbidden();
});

test('opening the submission link verifies the owner email', function () {
    Event::fake([Verified::class]);
    $submission = Submission::factory()->for(User::factory()->unverified())->create();

    $this->actingAs($submission->user)
        ->get(route('submissions.show', $submission))
        ->assertOk();

    expect($submission->user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

test('the upload requirements and deadline come from the settings', function () {
    Setting::put('paper_allowed_extensions', 'pptx');
    Setting::put('paper_max_size_mb', '15');
    Setting::put('require_statement_letter', '0');
    Setting::put('paper_deadline', '2026-10-19');
    $this->travelTo('2026-10-19 16:59:59'); // 23:59:59 WIB
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->get(route('submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->component('submissions/show')
            ->where('submission.uuid', $submission->uuid)
            ->where('requirements.paperExtensions', ['pptx'])
            ->where('requirements.maxSizeMb', 15)
            ->where('requirements.requireStatementLetter', false)
            ->where('paperDeadline', '2026-10-19T23:59:59+07:00')
            ->where('isClosed', false));

    $this->travelTo('2026-10-19 17:00:00');

    $this->actingAs($submission->user)
        ->get(route('submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('isClosed', true));
});

test('the submission page reopens the upload while a revision is open', function () {
    $submission = Submission::factory()->needsRevision()->create(['revision_deadline' => now()->addDay()]);

    $this->actingAs($submission->user)
        ->get(route('submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.isRevisionOpen', true)
            ->where('submission.revisionNote', 'Please use the official template.'));

    $this->travel(2)->days();

    $this->actingAs($submission->user)
        ->get(route('submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.isRevisionOpen', false));
});

test('the upload page offers the category paper template before the default one', function () {
    Setting::put('submission_template_path', 'settings/default.pdf');
    $category = AwardCategory::factory()->create(['paper_template_path' => 'categories/community.pdf']);
    $submission = Submission::factory()->for($category, 'awardCategory')->create();

    $this->actingAs($submission->user)
        ->get(route('submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.paperTemplateUrl', Storage::disk('public')->url('categories/community.pdf')));

    $category->forceFill(['paper_template_path' => null])->save();

    $this->actingAs($submission->user)
        ->get(route('submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.paperTemplateUrl', Storage::disk('public')->url('settings/default.pdf')));
});
