<?php

namespace App\Models;

use App\Enums\Announcement;
use App\Enums\Award;
use App\Enums\JudgingStage;
use App\Enums\ScoreRecapStage;
use App\Enums\SubmissionStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $award_category_id
 * @property string $initiative_title
 * @property string $initiative_description
 * @property SubmissionStatus $status
 * @property string|null $revision_note
 * @property Carbon|null $revision_deadline
 * @property string|null $disqualified_reason
 * @property Carbon|null $reviewed_at
 * @property int|null $reviewed_by
 * @property Carbon|null $notified_at
 * @property Carbon|null $terms_accepted_at
 * @property Carbon|null $confirmation_sent_at
 * @property string|null $paper_path
 * @property string|null $paper_original_name
 * @property string|null $statement_path
 * @property string|null $statement_original_name
 * @property Carbon|null $paper_uploaded_at
 * @property float|null $stage1_raw_score
 * @property float|null $stage1_score
 * @property int|null $stage1_rank
 * @property int|null $stage1_judges_submitted
 * @property int|null $stage1_judges_assigned
 * @property Carbon|null $stage1_calculated_at
 * @property float|null $stage2_raw_score
 * @property float|null $stage2_score
 * @property int|null $stage2_rank
 * @property int|null $stage2_judges_submitted
 * @property int|null $stage2_judges_assigned
 * @property Carbon|null $stage2_calculated_at
 * @property float|null $final_score
 * @property int|null $final_rank
 * @property Carbon|null $final_calculated_at
 * @property Award|null $award
 * @property Carbon|null $finalist_notified_at
 * @property Carbon|null $invitation_notified_at
 * @property Carbon|null $award_notified_at
 * @property-read bool|null $judge_has_scores
 * @property-read bool|null $judge_has_submitted
 * @property-read string|null $judge_scored_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'award_category_id',
    'initiative_title',
    'initiative_description',
    'status',
    'revision_note',
    'revision_deadline',
    'disqualified_reason',
    'reviewed_at',
    'reviewed_by',
    'notified_at',
    'terms_accepted_at',
    'confirmation_sent_at',
    'paper_path',
    'paper_original_name',
    'statement_path',
    'statement_original_name',
    'paper_uploaded_at',
])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, HasUuids;

    /**
     * Mirror the column default so a freshly created submission has a status before being reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'registered',
    ];

    /**
     * The public identifier lives in `uuid`; the primary key stays an auto-incrementing integer.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Submissions are addressed by their UUID in URLs (e.g. the personal link in the confirmation email).
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Once the paper is submitted its files are locked, unless the committee reopened them for a revision.
     */
    public function isPaperLocked(): bool
    {
        return $this->paper_uploaded_at !== null && ! $this->isRevisionOpen();
    }

    /**
     * The committee asked for a revision and its deadline has not passed yet (no deadline means no limit).
     */
    public function isRevisionOpen(): bool
    {
        return $this->status === SubmissionStatus::NeedsRevision
            && ($this->revision_deadline === null || now()->lessThanOrEqualTo($this->revision_deadline));
    }

    /**
     * The status the participant may see: a finalist stays "qualified" until the committee announces the category's
     * finalists. Loads `awardCategory`.
     */
    public function statusForParticipant(): SubmissionStatus
    {
        if ($this->status === SubmissionStatus::Finalist && ! $this->awardCategory->isAnnounced(Announcement::Finalists)) {
            return SubmissionStatus::Qualified;
        }

        return $this->status;
    }

    /**
     * The stored path and original name of a submitted file, or null when it was never uploaded.
     *
     * @param  'paper'|'statement'  $file
     * @return array{path: string, name: string}|null
     */
    public function submittedFile(string $file): ?array
    {
        [$path, $name] = $file === 'paper'
            ? [$this->paper_path, $this->paper_original_name]
            : [$this->statement_path, $this->statement_original_name];

        return $path === null ? null : ['path' => $path, 'name' => $name ?? basename($path)];
    }

    /**
     * Apply the admin participant table filters (search, category, status, sort) and eager load what the rows show.
     *
     * @param  Builder<$this>  $query
     * @param  array{search: string|null, category: int|null, status: string|null, sort: string}  $filters
     */
    #[Scope]
    protected function filteredForAdmin(Builder $query, array $filters): void
    {
        $query
            ->with(['user:id,name,email,phone,position,company_name,email_verified_at', 'awardCategory:id,name,applicant_type'])
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(fn (Builder $query) => $query
                    ->whereLike('initiative_title', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('company_name', "%{$search}%")));
            })
            ->when($filters['category'], fn (Builder $query, int $category) => $query->where('award_category_id', $category))
            ->when($filters['status'], fn (Builder $query, string $status) => $query->where('status', $status));

        $direction = str_starts_with($filters['sort'], '-') ? 'desc' : 'asc';

        $column = match (ltrim($filters['sort'], '-')) {
            'name' => User::query()->select('name')->whereColumn('users.id', 'submissions.user_id'),
            'category' => AwardCategory::query()->select('name')->whereColumn('award_categories.id', 'submissions.award_category_id'),
            default => ltrim($filters['sort'], '-'),
        };

        $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * Apply the Score Recap filters (stage, search, category, sort) and eager load what the rows show: the qualified
     * submissions and finalists for Stage 1, the finalists of categories with confirmed finalists for Stage 2 and Final.
     *
     * Sorting by `rank` groups the rows per category and puts unranked submissions last; rank and scores are read from
     * the stage's own columns.
     *
     * @param  Builder<$this>  $query
     * @param  array{stage: ScoreRecapStage, search: string|null, category: int|null, sort: string}  $filters
     */
    #[Scope]
    protected function scoreRecapForAdmin(Builder $query, array $filters): void
    {
        $prefix = $filters['stage']->prefix();

        $query
            ->when(
                $filters['stage']->listsFinalists(),
                fn (Builder $query) => $query
                    ->where('status', SubmissionStatus::Finalist)
                    ->whereHas('awardCategory', fn (Builder $category) => $category->whereNotNull('finalists_confirmed_at')),
                fn (Builder $query) => $query->whereIn('status', SubmissionStatus::rankable()),
            )
            ->with(['user:id,name,email,company_name', 'awardCategory:id,name'])
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(fn (Builder $query) => $query
                    ->whereLike('initiative_title', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereLike('company_name', "%{$search}%")));
            })
            ->when($filters['category'], fn (Builder $query, int $category) => $query->where('award_category_id', $category));

        $direction = str_starts_with($filters['sort'], '-') ? 'desc' : 'asc';
        $categoryName = AwardCategory::query()->select('name')->whereColumn('award_categories.id', 'submissions.award_category_id');

        match (ltrim($filters['sort'], '-')) {
            'rank' => $query
                ->orderBy($categoryName, $direction)
                ->orderByRaw("{$prefix}_rank is null")
                ->orderBy("{$prefix}_rank", $direction),
            'score' => $query->orderBy("{$prefix}_score", $direction),
            'raw_score' => $query->orderBy($filters['stage'] === ScoreRecapStage::Final ? 'final_score' : "{$prefix}_raw_score", $direction),
            'name' => $query->orderBy(User::query()->select('name')->whereColumn('users.id', 'submissions.user_id'), $direction),
            'category' => $query->orderBy($categoryName, $direction),
            default => $query->orderBy(ltrim($filters['sort'], '-'), $direction),
        };

        $query->orderBy('id', $direction);
    }

    /**
     * Limit to the submissions scored in the stage: qualified (or confirmed finalist) in desk evaluation, and only the
     * finalists of categories with confirmed finalists in pitching.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function scoredInStage(Builder $query, JudgingStage $stage): void
    {
        $query->when(
            $stage === JudgingStage::Pitching,
            fn (Builder $query) => $query
                ->where('status', SubmissionStatus::Finalist)
                ->whereHas('awardCategory', fn (Builder $category) => $category->whereNotNull('finalists_confirmed_at')),
            fn (Builder $query) => $query->whereIn('status', SubmissionStatus::rankable()),
        );
    }

    /**
     * Limit to the submissions a judge scores, in a category the judge is assigned to and not recused from: qualified
     * (or confirmed finalist) in desk evaluation, and only the finalists of categories with confirmed finalists in pitching.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function assignedToJudge(Builder $query, Judge $judge, JudgingStage $stage = JudgingStage::DeskEvaluation): void
    {
        $query
            ->scoredInStage($stage)
            ->whereHas('awardCategory.judges', fn (Builder $judges) => $judges
                ->where('judges.id', $judge->id)
                ->where('category_judges.is_recused', false));
    }

    /**
     * Add the judge's own scoring progress for the stage: `judge_has_scores`, `judge_has_submitted` and `judge_scored_at`.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withJudgeProgress(Builder $query, Judge $judge, JudgingStage $stage): void
    {
        $ownScores = fn (Builder $scores) => $scores->where('judge_id', $judge->id)->where('stage', $stage);

        $query
            ->withExists(['judgeScores as judge_has_scores' => $ownScores])
            ->withExists(['judgeScores as judge_has_submitted' => fn (Builder $scores) => $ownScores($scores)->whereNotNull('submitted_at')])
            ->withMax(['judgeScores as judge_scored_at' => $ownScores], 'updated_at');
    }

    /**
     * The judge's scoring status for this submission, read from the `withJudgeProgress` columns.
     *
     * @return 'not_started'|'draft'|'submitted'
     */
    public function judgeScoringStatus(): string
    {
        return match (true) {
            (bool) $this->judge_has_submitted => 'submitted',
            (bool) $this->judge_has_scores => 'draft',
            default => 'not_started',
        };
    }

    /**
     * Apply the judge's "My Submissions" filters (search, category, scoring status, sort) on top of `assignedToJudge`.
     *
     * @param  Builder<$this>  $query
     * @param  array{search: string|null, category: int|null, scoring_status: string|null, sort: string}  $filters
     */
    #[Scope]
    protected function filteredForJudge(Builder $query, Judge $judge, JudgingStage $stage, array $filters): void
    {
        $ownScores = fn (Builder $scores) => $scores->where('judge_id', $judge->id)->where('stage', $stage);

        $query
            ->with(['user:id,company_name', 'awardCategory:id,name,finalists_confirmed_at,awards_confirmed_at'])
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereLike('initiative_title', "%{$search}%")
                ->orWhereHas('user', fn (Builder $user) => $user->whereLike('company_name', "%{$search}%"))))
            ->when($filters['category'], fn (Builder $query, int $category) => $query->where('award_category_id', $category))
            ->when($filters['scoring_status'], fn (Builder $query, string $status) => match ($status) {
                'submitted' => $query->whereHas('judgeScores', fn (Builder $scores) => $ownScores($scores)->whereNotNull('submitted_at')),
                'draft' => $query
                    ->whereHas('judgeScores', $ownScores)
                    ->whereDoesntHave('judgeScores', fn (Builder $scores) => $ownScores($scores)->whereNotNull('submitted_at')),
                default => $query->whereDoesntHave('judgeScores', $ownScores),
            });

        $direction = str_starts_with($filters['sort'], '-') ? 'desc' : 'asc';

        $column = match (ltrim($filters['sort'], '-')) {
            'company' => User::query()->select('company_name')->whereColumn('users.id', 'submissions.user_id'),
            'category' => AwardCategory::query()->select('name')->whereColumn('award_categories.id', 'submissions.award_category_id'),
            'scored_at' => JudgeScore::query()->selectRaw('max(updated_at)')->whereColumn('judge_scores.submission_id', 'submissions.id')
                ->where('judge_id', $judge->id)->where('stage', $stage),
            default => ltrim($filters['sort'], '-'),
        };

        $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<AwardCategory, $this>
     */
    public function awardCategory(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class);
    }

    /**
     * All judges' scores for this submission; filter by judge and stage before exposing them.
     *
     * @return HasMany<JudgeScore, $this>
     */
    public function judgeScores(): HasMany
    {
        return $this->hasMany(JudgeScore::class);
    }

    /**
     * The finalist's time slot in their category's pitching session.
     *
     * @return HasOne<PitchingSlot, $this>
     */
    public function pitchingSlot(): HasOne
    {
        return $this->hasOne(PitchingSlot::class);
    }

    /**
     * The admin who made the administrative verification decision.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'revision_deadline' => 'datetime',
            'reviewed_at' => 'datetime',
            'notified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'paper_uploaded_at' => 'datetime',
            'stage1_raw_score' => 'float',
            'stage1_score' => 'float',
            'stage1_rank' => 'integer',
            'stage1_judges_submitted' => 'integer',
            'stage1_judges_assigned' => 'integer',
            'stage1_calculated_at' => 'datetime',
            'stage2_raw_score' => 'float',
            'stage2_score' => 'float',
            'stage2_rank' => 'integer',
            'stage2_judges_submitted' => 'integer',
            'stage2_judges_assigned' => 'integer',
            'stage2_calculated_at' => 'datetime',
            'final_score' => 'float',
            'final_rank' => 'integer',
            'final_calculated_at' => 'datetime',
            'award' => Award::class,
            'finalist_notified_at' => 'datetime',
            'invitation_notified_at' => 'datetime',
            'award_notified_at' => 'datetime',
        ];
    }
}
