<?php

namespace App\Actions\Overview;

use App\Enums\ApplicantType;
use App\Enums\JudgingStage;
use App\Enums\RegistrationStatus;
use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\JudgeScore;
use App\Models\Setting;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The numbers behind the admin Overview page, one method per section.
 */
class BuildAdminOverview
{
    /**
     * Days shown in the registration trend.
     */
    public const int TREND_DAYS = 30;

    /**
     * Events shown in the recent activity list.
     */
    public const int RECENT_ACTIVITY_LIMIT = 10;

    /**
     * How long a verification decision may wait for its email before it is flagged; the job normally sends it within seconds.
     */
    public const int EMAIL_GRACE_MINUTES = 10;

    /**
     * The scoring matrix of the stage judges see, computed once per request.
     *
     * @var array{stage: JudgingStage, rows: Collection<int, array{judge_id: int, name: string, has_account: bool, category_id: int, assigned: int, submitted: int, draft: int}>}|null
     */
    private ?array $scoring = null;

    /**
     * Registration status, active judging stage and the nearest upcoming deadline.
     *
     * @return array{registration: string, judgingStage: array{value: string, label: string}, nextDeadline: array{label: string, at: string}|null}
     */
    public function summary(): array
    {
        $stage = JudgingStage::current();

        $nextDeadline = null;

        foreach ([
            'Registration opens' => Setting::startOfDay('registration_opens_at'),
            'Registration closes' => Setting::endOfDay('registration_deadline'),
            'Paper submission closes' => Setting::endOfDay('paper_deadline'),
        ] as $label => $at) {
            if ($at !== null && $at->isFuture() && ($nextDeadline === null || $at->lessThan($nextDeadline['at']))) {
                $nextDeadline = ['label' => $label, 'at' => $at];
            }
        }

        return [
            'registration' => RegistrationStatus::current()->value,
            'judgingStage' => ['value' => $stage->value, 'label' => $stage->label()],
            'nextDeadline' => $nextDeadline === null ? null : [
                'label' => $nextDeadline['label'],
                'at' => $nextDeadline['at']->toIso8601String(),
            ],
        ];
    }

    /**
     * Total registrations, uploaded papers and submissions per status.
     *
     * @return array{registrations: int, papers: int, statuses: array<string, int>}
     */
    public function statusCounts(): array
    {
        $byStatus = Submission::query()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'registrations' => (int) $byStatus->sum(),
            'papers' => Submission::query()->whereNotNull('paper_uploaded_at')->count(),
            'statuses' => collect(SubmissionStatus::cases())
                ->mapWithKeys(fn (SubmissionStatus $status): array => [$status->value => (int) ($byStatus[$status->value] ?? 0)])
                ->all(),
        ];
    }

    /**
     * Every category with its submission counts, active judges and scoring progress in the stage judges see.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $scoring = $this->scoring()['rows']->groupBy('category_id');

        return array_values(AwardCategory::query()
            ->withCount([
                'submissions',
                'submissions as papers_count' => fn (Builder $query) => $query->whereNotNull('paper_uploaded_at'),
                'submissions as qualified_count' => fn (Builder $query) => $query->whereIn('status', SubmissionStatus::rankable()),
                'finalists',
                'judges' => fn (Builder $query) => $query->where('category_judges.is_recused', false),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'applicant_type', 'sort_order', 'assessment_template_id', 'paper_template_path', 'finalists_confirmed_at', 'awards_confirmed_at'])
            ->map(function (AwardCategory $category) use ($scoring): array {
                $rows = $scoring->get($category->id) ?? collect();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'applicant_type' => $category->applicant_type->value,
                    'submissions_count' => (int) $category->getAttribute('submissions_count'),
                    'papers_count' => (int) $category->getAttribute('papers_count'),
                    'qualified_count' => (int) $category->getAttribute('qualified_count'),
                    'finalists_count' => (int) $category->getAttribute('finalists_count'),
                    'judges_count' => (int) $category->getAttribute('judges_count'),
                    'has_assessment_template' => $category->assessment_template_id !== null,
                    'finalists_confirmed' => $category->isFinalistsConfirmed(),
                    'awards_confirmed' => $category->isAwardsConfirmed(),
                    'scoring' => [
                        'assigned' => (int) $rows->sum('assigned'),
                        'submitted' => (int) $rows->sum('submitted'),
                    ],
                ];
            })
            ->all());
    }

    /**
     * The open tasks for the committee; only items with something to do are returned.
     *
     * @return list<array{key: string, count: int, label: string, href: string}>
     */
    public function attention(): array
    {
        $papers = fn (?SubmissionStatus $status = null): string => route('admin.participants.papers.index', array_filter(['status' => $status?->value]), absolute: false);

        $categoriesWithoutPaperTemplate = Setting::publicFileUrl('submission_template_path') === null
            ? AwardCategory::query()->whereNull('paper_template_path')->count()
            : 0;

        $items = [
            [
                'key' => 'awaiting_verification',
                'count' => Submission::query()->where('status', SubmissionStatus::UnderReview)->count(),
                'label' => 'Papers awaiting administrative verification',
                'href' => $papers(SubmissionStatus::UnderReview),
            ],
            [
                'key' => 'overdue_revisions',
                'count' => Submission::query()
                    ->where('status', SubmissionStatus::NeedsRevision)
                    ->where('revision_deadline', '<', now())
                    ->count(),
                'label' => 'Revisions past their deadline',
                'href' => $papers(SubmissionStatus::NeedsRevision),
            ],
            [
                'key' => 'unsent_decision_emails',
                'count' => Submission::query()
                    ->whereIn('status', SubmissionStatus::verificationDecisions())
                    ->whereNull('notified_at')
                    ->where('reviewed_at', '<=', now()->subMinutes(self::EMAIL_GRACE_MINUTES))
                    ->count(),
                'label' => 'Verification emails not sent yet (is the queue worker running?)',
                'href' => $papers(),
            ],
            [
                'key' => 'categories_without_judges',
                'count' => AwardCategory::query()
                    ->whereDoesntHave('judges', fn (Builder $query) => $query->where('category_judges.is_recused', false))
                    ->count(),
                'label' => 'Categories without an active judge',
                'href' => route('admin.judges.index', absolute: false),
            ],
            [
                'key' => 'categories_without_assessment_template',
                'count' => AwardCategory::query()->whereNull('assessment_template_id')->count(),
                'label' => 'Categories without an assessment template',
                'href' => route('admin.categories.index', absolute: false),
            ],
            [
                'key' => 'categories_without_paper_template',
                'count' => $categoriesWithoutPaperTemplate,
                'label' => 'Categories without a paper template (no default template either)',
                'href' => route('admin.categories.index', absolute: false),
            ],
            [
                'key' => 'unscheduled_finalists',
                'count' => Submission::query()
                    ->where('status', SubmissionStatus::Finalist)
                    ->whereHas('awardCategory', fn (Builder $query) => $query->whereNotNull('finalists_confirmed_at'))
                    ->whereDoesntHave('pitchingSlot')
                    ->count(),
                'label' => 'Finalists without a pitching slot',
                'href' => route('admin.pitching.index', absolute: false),
            ],
        ];

        return array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0));
    }

    /**
     * Scoring progress per judge in the stage judges see, judges with the most work left first.
     *
     * @return array{stage: array{value: string, label: string}, total: array{assigned: int, submitted: int, draft: int}, judges: list<array{id: int, name: string, has_account: bool, assigned: int, submitted: int, draft: int}>}
     */
    public function judgeProgress(): array
    {
        ['stage' => $stage, 'rows' => $rows] = $this->scoring();

        $judges = array_values($rows
            ->groupBy('judge_id')
            ->map(fn (Collection $judgeRows): array => [
                'id' => (int) $judgeRows->first()['judge_id'],
                'name' => (string) $judgeRows->first()['name'],
                'has_account' => (bool) $judgeRows->first()['has_account'],
                'assigned' => (int) $judgeRows->sum('assigned'),
                'submitted' => (int) $judgeRows->sum('submitted'),
                'draft' => (int) $judgeRows->sum('draft'),
            ])
            ->sortBy([
                fn (array $a, array $b): int => ($b['assigned'] - $b['submitted']) <=> ($a['assigned'] - $a['submitted']),
                fn (array $a, array $b): int => $a['name'] <=> $b['name'],
            ])
            ->all());

        return [
            'stage' => ['value' => $stage->value, 'label' => $stage->label()],
            'total' => [
                'assigned' => (int) $rows->sum('assigned'),
                'submitted' => (int) $rows->sum('submitted'),
                'draft' => (int) $rows->sum('draft'),
            ],
            'judges' => $judges,
        ];
    }

    /**
     * Registrations and paper uploads per day (WIB) over the last `TREND_DAYS` days, or since registration opened.
     *
     * @return list<array{date: string, registrations: int, papers: int}>
     */
    public function trend(): array
    {
        $timezone = Setting::EVENT_TIMEZONE;
        $end = CarbonImmutable::now($timezone)->startOfDay();
        $start = $end->subDays(self::TREND_DAYS - 1);

        $opensAt = Setting::startOfDay('registration_opens_at')?->setTimezone($timezone)->startOfDay();

        if ($opensAt !== null && $opensAt->greaterThan($start) && $opensAt->lessThanOrEqualTo($end)) {
            $start = $opensAt;
        }

        $since = $start->setTimezone(config('app.timezone'));

        $perDay = fn (string $column): Collection => Submission::query()
            ->where($column, '>=', $since)
            ->get([$column])
            ->countBy(fn (Submission $submission): string => CarbonImmutable::parse($submission->getAttribute($column))->setTimezone($timezone)->toDateString());

        $registrations = $perDay('created_at');
        $papers = $perDay('paper_uploaded_at');

        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $date = $day->toDateString();

            $days[] = [
                'date' => $date,
                'registrations' => (int) ($registrations[$date] ?? 0),
                'papers' => (int) ($papers[$date] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * The latest registrations, paper uploads and verification decisions, newest first.
     *
     * @return list<array{id: string, type: string, at: string, title: string, participant: string, category: string|null, status: string, uuid: string, actor: string|null}>
     */
    public function recentActivity(): array
    {
        $latest = fn (string $column): Collection => Submission::query()
            ->with(['user:id,name,company_name', 'awardCategory:id,name', 'reviewer:id,name'])
            ->whereNotNull($column)
            ->latest($column)
            ->latest('id')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get();

        $events = collect([
            'registered' => 'created_at',
            'paper_uploaded' => 'paper_uploaded_at',
            'reviewed' => 'reviewed_at',
        ])->flatMap(fn (string $column, string $type): Collection => $latest($column)->map(fn (Submission $submission): array => [
            'id' => "{$type}-{$submission->id}",
            'type' => $type,
            'at' => CarbonImmutable::parse($submission->getAttribute($column))->toIso8601String(),
            'title' => $submission->initiative_title,
            'participant' => $submission->user->company_name ?? $submission->user->name,
            'category' => $submission->awardCategory?->name,
            'status' => $submission->status->value,
            'uuid' => $submission->uuid,
            'actor' => $type === 'reviewed' ? $submission->reviewer?->name : null,
        ]));

        return array_values($events
            ->sortByDesc('at')
            ->take(self::RECENT_ACTIVITY_LIMIT)
            ->all());
    }

    /**
     * Registrations and uploaded papers per applicant type of the category.
     *
     * @return list<array{type: string, registrations: int, papers: int}>
     */
    public function applicantTypes(): array
    {
        $counts = DB::table('submissions')
            ->join('award_categories', 'award_categories.id', '=', 'submissions.award_category_id')
            ->selectRaw('award_categories.applicant_type as type, count(*) as registrations, sum(case when submissions.paper_uploaded_at is not null then 1 else 0 end) as papers')
            ->groupBy('award_categories.applicant_type')
            ->get()
            ->keyBy('type');

        return array_map(fn (ApplicantType $type): array => [
            'type' => $type->value,
            'registrations' => (int) ($counts->get($type->value)->registrations ?? 0),
            'papers' => (int) ($counts->get($type->value)->papers ?? 0),
        ], ApplicantType::cases());
    }

    /**
     * One row per active (not recused) judge assignment: how many of the category's submissions the judge scores in the
     * stage judges see, and how many of them are submitted or still in draft.
     *
     * @return array{stage: JudgingStage, rows: Collection<int, array{judge_id: int, name: string, has_account: bool, category_id: int, assigned: int, submitted: int, draft: int}>}
     */
    private function scoring(): array
    {
        if ($this->scoring !== null) {
            return $this->scoring;
        }

        $stage = JudgingStage::forJudges();

        $assignments = DB::table('category_judges')
            ->join('judges', 'judges.id', '=', 'category_judges.judge_id')
            ->where('category_judges.is_recused', false)
            ->orderBy('judges.name')
            ->get(['judges.id as judge_id', 'judges.name', 'judges.user_id', 'category_judges.award_category_id']);

        $submissions = Submission::query()
            ->scoredInStage($stage)
            ->get(['id', 'award_category_id'])
            ->groupBy('award_category_id')
            ->map(fn (Collection $categorySubmissions): Collection => $categorySubmissions->pluck('id'));

        $scores = JudgeScore::query()
            ->where('stage', $stage)
            ->whereIn('submission_id', Submission::query()->scoredInStage($stage)->select('id'))
            ->toBase()
            ->selectRaw('judge_id, submission_id, max(case when submitted_at is not null then 1 else 0 end) as is_submitted')
            ->groupBy('judge_id', 'submission_id')
            ->get()
            ->groupBy('judge_id')
            ->map(fn (Collection $judgeScores): Collection => $judgeScores->mapWithKeys(fn (object $score): array => [(int) $score->submission_id => (bool) $score->is_submitted]));

        $rows = $assignments->map(function (object $assignment) use ($submissions, $scores): array {
            /** @var Collection<int, int> $categorySubmissions */
            $categorySubmissions = $submissions->get($assignment->award_category_id) ?? collect();
            /** @var Collection<int, bool> $judgeScores */
            $judgeScores = $scores->get($assignment->judge_id) ?? collect();
            $scored = $judgeScores->only($categorySubmissions->all());

            return [
                'judge_id' => (int) $assignment->judge_id,
                'name' => (string) $assignment->name,
                'has_account' => $assignment->user_id !== null,
                'category_id' => (int) $assignment->award_category_id,
                'assigned' => $categorySubmissions->count(),
                'submitted' => $scored->filter()->count(),
                'draft' => $scored->reject(fn (bool $isSubmitted): bool => $isSubmitted)->count(),
            ];
        });

        return $this->scoring = ['stage' => $stage, 'rows' => $rows];
    }
}
