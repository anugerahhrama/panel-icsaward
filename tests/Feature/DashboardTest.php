<?php

use App\Enums\Award;
use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('admins and superadmins are sent to the admin dashboard', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
})->with(['admin', 'superadmin']);

test('judges are sent to the judge overview', function () {
    $this->actingAs(User::factory()->judge()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('judge.dashboard'));
});

test('participants see only their own submissions', function () {
    $user = User::factory()->create();
    $registered = Submission::factory()->for($user)->create();
    $submitted = Submission::factory()->for($user)->paperSubmitted()->create();
    Submission::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('submissions', 2)
            ->where('submissions.0.uuid', $registered->uuid)
            ->where('submissions.0.paperUrl', null)
            ->where('submissions.1.uuid', $submitted->uuid)
            ->where('submissions.1.status', 'under_review')
            ->where('submissions.1.paperUrl', route('submissions.files.show', [$submitted, 'paper'])));
});

test('the deadline and timeline dates come from the settings', function () {
    Setting::put('paper_deadline', '2026-10-19');
    Setting::put('timeline_pitching', '6 – 10 November 2026');
    $this->travelTo('2026-10-19 17:00:00'); // 00:00 WIB the next day
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('paperDeadline', '2026-10-19T23:59:59+07:00')
            ->where('isClosed', true)
            ->has('timeline', 5)
            ->where('timeline.3.title', 'Pitching session')
            ->where('timeline.3.date', '6 – 10 November 2026')
            ->where('timeline.4.date', null));
});

test('the timeline uses the committee text and falls back to the default copy', function () {
    Setting::put('timeline_desk_evaluation_title', 'Penilaian dewan juri');
    Setting::put('timeline_desk_evaluation_description', 'Dewan juri menilai paper Anda.');
    Setting::put('timeline_pitching_title', '');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeline.1.title', 'Penilaian dewan juri')
            ->where('timeline.1.description', 'Dewan juri menilai paper Anda.')
            ->where('timeline.3.title', 'Pitching session')
            ->where('timeline.3.description', 'Finalists present their initiative to the judges.'));
});

test('participants see the committee revision note and deadline', function () {
    $submission = Submission::factory()->paperSubmitted()->create([
        'status' => SubmissionStatus::NeedsRevision,
        'revision_note' => 'Please use the official template.',
        'revision_deadline' => '2026-10-22 16:59:59',
    ]);

    $this->actingAs($submission->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.status', 'needs_revision')
            ->where('submissions.0.revisionNote', 'Please use the official template.')
            ->where('submissions.0.revisionDeadline', '2026-10-22T16:59:59+00:00')
            ->where('submissions.0.isRevisionOpen', true)
            ->where('submissions.0.disqualifiedReason', null));
});

test('the revision closes for participants after the revision deadline', function () {
    $submission = Submission::factory()->needsRevision()->create(['revision_deadline' => now()->subSecond()]);

    $this->actingAs($submission->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.status', 'needs_revision')
            ->where('submissions.0.isRevisionOpen', false));
});

test('each submission offers the paper template of its category or the default one', function () {
    Setting::put('submission_template_path', 'settings/default.pdf');
    $user = User::factory()->create();
    Submission::factory()->for($user)->for(AwardCategory::factory()->state(['paper_template_path' => 'categories/community.pdf']), 'awardCategory')->create();
    Submission::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.paperTemplateUrl', Storage::disk('public')->url('categories/community.pdf'))
            ->where('submissions.1.paperTemplateUrl', Storage::disk('public')->url('settings/default.pdf')));
});

test('finalists see their pitching schedule once the session is set up', function () {
    $user = User::factory()->create();
    $category = AwardCategory::factory()->finalistsAnnounced()->create();
    $scheduled = Submission::factory()->finalist()->for($user)->for($category, 'awardCategory')->create();
    $unscheduled = Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    $session = PitchingSession::factory()->for($category, 'awardCategory')->create([
        'scheduled_at' => '2026-11-06 02:00:00',
        'location' => 'Ballroom A',
        'meeting_link' => 'https://meet.example.com/icsa',
    ]);
    PitchingSlot::factory()->for($session)->for($scheduled)->create(['starts_at' => '2026-11-06 02:20:00']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.pitching', [
                'scheduledAt' => '2026-11-06T02:00:00+00:00',
                'startsAt' => '2026-11-06T02:20:00+00:00',
                'location' => 'Ballroom A',
                'meetingLink' => 'https://meet.example.com/icsa',
            ]));

    $this->actingAs($unscheduled->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.pitching.scheduledAt', '2026-11-06T02:00:00+00:00')
            ->where('submissions.0.pitching.startsAt', null));
});

test('the pitching schedule is hidden from submissions that are not announced finalists', function () {
    $user = User::factory()->create();
    $category = AwardCategory::factory()->finalistsAnnounced()->create();
    Submission::factory()->qualified()->for($user)->for($category, 'awardCategory')->create();
    Submission::factory()->finalist()->for($user)->create();
    PitchingSession::factory()->for($category, 'awardCategory')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.pitching', null)
            ->where('submissions.1.pitching', null));
});

test('finalists stay qualified for participants until the finalists are announced', function () {
    $submission = Submission::factory()->finalist()->for(AwardCategory::factory()->finalistsConfirmed(), 'awardCategory')->create();
    PitchingSession::factory()->for($submission->awardCategory, 'awardCategory')->create();

    $this->actingAs($submission->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.status', 'qualified')
            ->where('submissions.0.notSelected', false)
            ->where('submissions.0.pitching', null));

    $this->get(route('submissions.show', $submission))
        ->assertInertia(fn (Assert $page) => $page->where('submission.status', 'qualified'));
});

test('once the finalists are announced, the other qualified submissions are not selected', function () {
    $category = AwardCategory::factory()->finalistsAnnounced()->create();
    $finalist = Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    $notSelected = Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($finalist->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.status', 'finalist')
            ->where('submissions.0.awardingNight', null)
            ->where('submissions.0.award', null));

    $this->actingAs($notSelected->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.status', 'qualified')
            ->where('submissions.0.notSelected', true));
});

test('finalists see the Awarding Night invitation, and their award only once the winners are announced', function () {
    Setting::put('timeline_awarding_night', '20 November 2026 · Mason Pine Hotel');
    $invited = Submission::factory()->finalist()->for(AwardCategory::factory()->invitationsSent()->awardsConfirmed(), 'awardCategory')
        ->create(['award' => Award::Gold]);
    $winner = Submission::factory()->finalist()->for(AwardCategory::factory()->winnersAnnounced(), 'awardCategory')
        ->create(['award' => Award::Bronze]);

    $this->actingAs($invited->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissions.0.awardingNight', ['details' => '20 November 2026 · Mason Pine Hotel'])
            ->where('submissions.0.award', null));

    $this->actingAs($winner->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('submissions.0.award', 'bronze'));
});
