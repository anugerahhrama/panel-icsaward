<?php

use App\Enums\JudgingStage;
use App\Models\AwardCategory;
use App\Models\JudgeScore;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('the overview counts submissions per status and category', function () {
    $category = AwardCategory::factory()->create();
    Submission::factory()->for($category, 'awardCategory')->create();
    Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create();
    Submission::factory()->qualified()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('statusCounts.registrations', 4)
            ->where('statusCounts.papers', 3)
            ->where('statusCounts.statuses.registered', 1)
            ->where('statusCounts.statuses.under_review', 1)
            ->where('statusCounts.statuses.qualified', 2)
            ->where('statusCounts.statuses.finalist', 0)
            ->where('categories', fn ($categories) => collect($categories)->firstWhere('id', $category->id)['submissions_count'] === 2
                && collect($categories)->firstWhere('id', $category->id)['papers_count'] === 1)
            ->missing('trend')
            ->missing('recentActivity'));
});

test('the attention panel lists only open tasks', function () {
    $category = categoryWithRubric();
    judgeAssignedTo($category);
    categoryWithRubric();
    Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create();
    Submission::factory()->needsRevision()->for($category, 'awardCategory')->create(['revision_deadline' => now()->subDay()]);
    Submission::factory()->needsRevision()->for($category, 'awardCategory')->create();

    $confirmed = categoryWithRubric(finalistsConfirmed: true);
    judgeAssignedTo($confirmed);
    [$scheduled] = Submission::factory()->finalist()->for($confirmed, 'awardCategory')->count(2)->create()->all();
    PitchingSlot::factory()
        ->for(PitchingSession::factory()->for($confirmed, 'awardCategory'))
        ->create(['submission_id' => $scheduled->id]);

    $this->actingAs(User::factory()->superadmin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attention', function ($attention) {
            $counts = collect($attention)->pluck('count', 'key');

            return $counts->get('awaiting_verification') === 1
                && $counts->get('overdue_revisions') === 1
                && $counts->get('categories_without_judges') === 1
                && $counts->get('unscheduled_finalists') === 1
                && ! $counts->has('categories_without_assessment_template');
        }));
});

test('decision emails that never went out are flagged after the grace period', function () {
    Submission::factory()->qualified()->create(['reviewed_at' => now()->subHour()]);
    Submission::factory()->qualified()->create(['reviewed_at' => now()]);
    Submission::factory()->qualified()->create(['reviewed_at' => now()->subHour(), 'notified_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('attention', fn ($attention) => collect($attention)->pluck('count', 'key')->get('unsent_decision_emails') === 1));
});

test('judge progress counts submitted and draft scores of active assignments only', function () {
    Setting::put(JudgingStage::SETTING_KEY, JudgingStage::DeskEvaluation->value);
    $category = categoryWithRubric();
    [$first, $second] = Submission::factory()->qualified()->for($category, 'awardCategory')->count(2)->create()->all();
    Submission::factory()->paperSubmitted()->for($category, 'awardCategory')->create();

    $judge = judgeAssignedTo($category);
    submitScores($judge, $first, 80);
    JudgeScore::factory()->create([
        'submission_id' => $second->id,
        'judge_id' => $judge->id,
        'scoring_criterion_id' => $category->assessmentTemplate->criteria()->value('id'),
    ]);

    $recused = judgeAssignedTo($category, recused: true);
    submitScores($recused, $second, 70);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('judgeProgress.stage.value', 'desk_evaluation')
            ->where('judgeProgress.total', ['assigned' => 2, 'submitted' => 1, 'draft' => 1])
            ->has('judgeProgress.judges', 1)
            ->where('judgeProgress.judges.0.id', $judge->id)
            ->where('categories', fn ($categories) => collect($categories)->firstWhere('id', $category->id)['scoring'] === ['assigned' => 2, 'submitted' => 1]));
});

test('judge progress follows the pitching stage once finalists are confirmed', function () {
    $category = categoryWithRubric(finalistsConfirmed: true);
    Submission::factory()->finalist()->for($category, 'awardCategory')->create();
    Submission::factory()->qualified()->for($category, 'awardCategory')->create();
    judgeAssignedTo($category);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('judgeProgress.stage.value', 'pitching')
            ->where('judgeProgress.total.assigned', 1));
});

test('the trend groups registrations and papers by day in WIB', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', Setting::EVENT_TIMEZONE));

    Submission::factory()->create(['created_at' => CarbonImmutable::parse('2026-10-08 23:30', Setting::EVENT_TIMEZONE)->utc()]);
    Submission::factory()->paperSubmitted()->create([
        'created_at' => CarbonImmutable::parse('2026-10-09 06:00', Setting::EVENT_TIMEZONE)->utc(),
        'paper_uploaded_at' => CarbonImmutable::parse('2026-10-10 00:30', Setting::EVENT_TIMEZONE)->utc(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('activity', fn (Assert $reload) => $reload
            ->has('trend', 30)
            ->where('trend', function ($trend) {
                $days = collect($trend)->keyBy('date');

                return $days['2026-10-08']['registrations'] === 1
                    && $days['2026-10-09']['registrations'] === 1
                    && $days['2026-10-09']['papers'] === 0
                    && $days['2026-10-10']['papers'] === 1;
            })
            ->has('recentActivity', 3)
            ->where('recentActivity.0.type', 'paper_uploaded')));
});

test('the trend starts on the day registration opened when that is more recent', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', Setting::EVENT_TIMEZONE));
    Setting::put('registration_opens_at', '2026-10-06');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('activity', fn (Assert $reload) => $reload
            ->has('trend', 5)
            ->where('trend.0.date', '2026-10-06')));
});

test('the summary shows the nearest upcoming deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10 12:00', Setting::EVENT_TIMEZONE));
    Setting::put('registration_deadline', '2026-10-09');
    Setting::put('paper_deadline', '2026-10-20');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.nextDeadline.label', 'Paper submission closes')
            ->where('summary.nextDeadline.at', CarbonImmutable::parse('2026-10-20 23:59:59', Setting::EVENT_TIMEZONE)->toIso8601String()));
});

test('applicant types split registrations and papers', function () {
    Submission::factory()->paperSubmitted()->for(AwardCategory::factory()->individual(), 'awardCategory')->create();
    Submission::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('applicantTypes', [
            ['type' => 'organization', 'registrations' => 1, 'papers' => 0],
            ['type' => 'individual', 'registrations' => 1, 'papers' => 1],
        ]));
});
