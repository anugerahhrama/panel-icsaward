<?php

use App\Enums\Announcement;
use App\Enums\Award;
use App\Jobs\SendAnnouncement;
use App\Mail\CategoryAnnouncement;
use App\Models\AwardCategory;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('judges and participants cannot see or make announcements', function (string $role) {
    Queue::fake();
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    $user = $role === 'judge' ? User::factory()->judge()->create() : User::factory()->create();

    $this->actingAs($user)->get(route('admin.announcements.index'))->assertForbidden();
    $this->post(route('admin.announcements.store', [$category, 'finalists']))->assertForbidden();

    expect($category->fresh()->finalists_announced_at)->toBeNull();
    Queue::assertNothingPushed();
})->with(['judge', 'participant']);

test('the announcements page shows each step and why it is not available yet', function () {
    AwardCategory::factory()->create(['name' => 'Open category', 'sort_order' => 1]);
    $announced = AwardCategory::factory()->finalistsAnnounced()->create(['sort_order' => 2]);
    Submission::factory()->finalist()->for($announced, 'awardCategory')->count(2)->create(['finalist_notified_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.announcements.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/announcements/index')
            ->where('categories.0.announcements.finalists.blocked_reason', 'Confirm the finalists in Score Recap first.')
            ->where('categories.1.announcements.finalists.recipients_count', 2)
            ->where('categories.1.announcements.finalists.sent_count', 2)
            ->where('categories.1.announcements.invitations.blocked_reason', null)
            ->where('categories.1.announcements.winners.blocked_reason', 'Confirm the awards in Score Recap first.'));
});

test('announcing the finalists records it and queues an email to each finalist only', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $category = AwardCategory::factory()->finalistsConfirmed()->create();
    $finalists = Submission::factory()->finalist()->for($category, 'awardCategory')->count(2)->create();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();

    $this->actingAs($admin)
        ->post(route('admin.announcements.store', [$category, 'finalists']))
        ->assertInertiaFlash('toast.message', "Finalists announced for {$category->name}. 2 emails queued.");

    expect($category->fresh())
        ->finalists_announced_at->not->toBeNull()
        ->finalists_announced_by->toBe($admin->id);
    Queue::assertPushed(SendAnnouncement::class, 2);
    Queue::assertPushed(SendAnnouncement::class, fn (SendAnnouncement $job): bool => $job->announcement === Announcement::Finalists
        && $finalists->contains($job->submission));
});

test('winners are announced only to the award recipients', function () {
    Queue::fake();
    $category = AwardCategory::factory()->invitationsSent()->awardsConfirmed()->create();
    $winner = Submission::factory()->finalist()->for($category, 'awardCategory')->create(['award' => Award::Gold]);
    Submission::factory()->finalist()->for($category, 'awardCategory')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.announcements.store', [$category, 'winners']))
        ->assertInertiaFlash('toast.type', 'success');

    Queue::assertPushed(SendAnnouncement::class, 1);
    Queue::assertPushed(SendAnnouncement::class, fn (SendAnnouncement $job): bool => $job->submission->is($winner));
});

test('an announcement is refused until the previous step is done', function (string $announcement, string $state, string $message) {
    Queue::fake();
    $factory = AwardCategory::factory();
    $category = ($state === 'none' ? $factory : $factory->{$state}())->create();
    Submission::factory()->finalist()->for($category, 'awardCategory')->create(['award' => Award::Gold]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.announcements.store', [$category, $announcement]))
        ->assertInertiaFlash('toast.message', $message);

    expect($category->fresh()->isAnnounced(Announcement::from($announcement)))->toBeFalse();
    Queue::assertNothingPushed();
})->with([
    'finalists before confirmation' => ['finalists', 'none', 'Confirm the finalists in Score Recap first.'],
    'invitations before the finalists are announced' => ['invitations', 'finalistsConfirmed', 'Announce the finalists first.'],
    'winners before the awards are confirmed' => ['winners', 'invitationsSent', 'Confirm the awards in Score Recap first.'],
    'winners before the invitations' => ['winners', 'awardsConfirmed', 'Send the Awarding Night invitations first.'],
]);

test('an announcement is made only once', function () {
    Queue::fake();
    $category = AwardCategory::factory()->finalistsAnnounced()->create();
    Submission::factory()->finalist()->for($category, 'awardCategory')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.announcements.store', [$category, 'finalists']))
        ->assertInertiaFlash('toast.type', 'error');

    Queue::assertNothingPushed();
});

test('an unknown announcement type is not found', function () {
    $category = AwardCategory::factory()->finalistsConfirmed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.announcements.store', [$category, 'everything']))
        ->assertNotFound();
});

test('the announcement email is sent once even when the job runs twice', function () {
    Mail::fake();
    $submission = Submission::factory()->finalist()->for(AwardCategory::factory()->finalistsAnnounced(), 'awardCategory')->create();

    (new SendAnnouncement($submission, Announcement::Finalists))->handle();
    (new SendAnnouncement($submission, Announcement::Finalists))->handle();

    Mail::assertSentCount(1);
    Mail::assertSent(CategoryAnnouncement::class, fn (CategoryAnnouncement $mail): bool => $mail->hasTo($submission->user->email));
    expect($submission->fresh()->finalist_notified_at)->not->toBeNull();
});

test('a failed announcement send releases the claim so the retry can deliver it', function () {
    Mail::shouldReceive('to->send')->andThrow(new RuntimeException('SMTP down'));
    $submission = Submission::factory()->finalist()->for(AwardCategory::factory()->invitationsSent(), 'awardCategory')->create();

    expect(fn () => (new SendAnnouncement($submission, Announcement::Invitations))->handle())->toThrow(RuntimeException::class);
    expect($submission->fresh()->invitation_notified_at)->toBeNull();
});

test('the finalist email escapes placeholders and includes the pitching schedule in WIB', function () {
    Setting::put('finalist_announcement_email_body', 'Dear {{name}}, {{pitching_schedule}}. {{dashboard_link}}');
    $category = AwardCategory::factory()->finalistsAnnounced()->create();
    $submission = Submission::factory()
        ->for(User::factory()->state(['name' => 'Ayu <b>Lestari</b>']))
        ->for($category, 'awardCategory')
        ->finalist()
        ->create();
    $session = PitchingSession::factory()->for($category, 'awardCategory')->create([
        'scheduled_at' => '2026-11-06 02:00:00',
        'location' => 'Ballroom A',
        'meeting_link' => null,
    ]);
    PitchingSlot::factory()->for($session)->for($submission)->create(['starts_at' => '2026-11-06 02:20:00']);

    $mail = new CategoryAnnouncement($submission->fresh(), Announcement::Finalists);

    $mail->assertSeeInHtml('Dear Ayu &lt;b&gt;Lestari&lt;/b&gt;', false);
    $mail->assertSeeInHtml('6 November 2026, 09:20 WIB, Ballroom A');
    $mail->assertSeeInHtml(route('dashboard'));
});

test('the invitation does not reveal the award, the winner email does', function () {
    Setting::put('timeline_awarding_night', '20 November 2026 · Mason Pine Hotel');
    $submission = Submission::factory()
        ->for(AwardCategory::factory()->winnersAnnounced(), 'awardCategory')
        ->finalist()
        ->create(['award' => Award::Silver]);

    $invitation = new CategoryAnnouncement($submission, Announcement::Invitations);
    $invitation->assertSeeInHtml('20 November 2026 · Mason Pine Hotel');
    $invitation->assertDontSeeInHtml('Silver');

    $winner = new CategoryAnnouncement($submission, Announcement::Winners);
    $winner->assertHasSubject('ICS Award 2026: Congratulations on your Silver award');
    $winner->assertSeeInHtml('Silver');
});
