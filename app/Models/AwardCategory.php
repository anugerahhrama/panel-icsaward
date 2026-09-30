<?php

namespace App\Models;

use App\Enums\ApplicantType;
use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AwardCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property ApplicantType $applicant_type
 * @property int|null $assessment_template_id
 * @property string|null $paper_template_path
 * @property string|null $paper_template_name
 * @property-read string|null $paper_template_url
 * @property-read int|null $finalists_count
 * @property int $sort_order
 * @property CarbonImmutable|null $finalists_confirmed_at
 * @property int|null $finalists_confirmed_by
 * @property CarbonImmutable|null $awards_confirmed_at
 * @property int|null $awards_confirmed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'applicant_type', 'assessment_template_id', 'sort_order'])]
#[Hidden(['paper_template_path'])]
class AwardCategory extends Model
{
    /** @use HasFactory<AwardCategoryFactory> */
    use HasFactory;

    /**
     * Paper templates are public downloads for participants.
     */
    public const string PAPER_TEMPLATE_DISK = 'public';

    /**
     * Mirror the column default so a freshly created category has an applicant type before being reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'applicant_type' => 'organization',
    ];

    /**
     * The rubric judges use to score submissions in this category.
     *
     * @return BelongsTo<AssessmentTemplate, $this>
     */
    public function assessmentTemplate(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class);
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * The judge scores given on this category's submissions, across judges and stages.
     *
     * @return HasManyThrough<JudgeScore, Submission, $this>
     */
    public function judgeScores(): HasManyThrough
    {
        return $this->hasManyThrough(JudgeScore::class, Submission::class);
    }

    /**
     * The judges assigned to this category, including recused ones.
     *
     * @return BelongsToMany<Judge, $this>
     */
    public function judges(): BelongsToMany
    {
        return $this->belongsToMany(Judge::class, 'category_judges')
            ->withPivot('is_recused')
            ->withTimestamps();
    }

    /**
     * The submissions the committee confirmed as finalists.
     *
     * @return HasMany<Submission, $this>
     */
    public function finalists(): HasMany
    {
        return $this->hasMany(Submission::class)->where('status', SubmissionStatus::Finalist);
    }

    /**
     * The admin who confirmed the finalists.
     *
     * @return BelongsTo<User, $this>
     */
    public function finalistsConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalists_confirmed_by')->withTrashed();
    }

    /**
     * The admin who confirmed the awards.
     *
     * @return BelongsTo<User, $this>
     */
    public function awardsConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awards_confirmed_by')->withTrashed();
    }

    /**
     * The session in which the category's finalists pitch.
     *
     * @return HasOne<PitchingSession, $this>
     */
    public function pitchingSession(): HasOne
    {
        return $this->hasOne(PitchingSession::class);
    }

    /**
     * Whether the finalists are confirmed, which freezes the category's Stage 1 results.
     */
    public function isFinalistsConfirmed(): bool
    {
        return $this->finalists_confirmed_at !== null;
    }

    /**
     * Whether the awards are confirmed, which freezes the category's Stage 2 and final results.
     */
    public function isAwardsConfirmed(): bool
    {
        return $this->awards_confirmed_at !== null;
    }

    /**
     * Whether the category's scores of a stage are frozen: desk evaluation once the finalists are confirmed, pitching
     * once the awards are confirmed.
     */
    public function isScoringFrozen(JudgingStage $stage): bool
    {
        return match ($stage) {
            JudgingStage::DeskEvaluation => $this->isFinalistsConfirmed(),
            JudgingStage::Pitching => $this->isAwardsConfirmed(),
            JudgingStage::Closed => false,
        };
    }

    /**
     * Limit to the categories whose finalists are confirmed.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function finalistsConfirmed(Builder $query): void
    {
        $query->whereNotNull('finalists_confirmed_at');
    }

    /**
     * Limit to the categories whose awards are confirmed.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function awardsConfirmed(Builder $query): void
    {
        $query->whereNotNull('awards_confirmed_at');
    }

    /**
     * The category's own paper template; callers fall back to the default template from the settings.
     *
     * @return Attribute<string|null, never>
     */
    protected function paperTemplateUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->paper_template_path === null ? null : Storage::disk(self::PAPER_TEMPLATE_DISK)->url($this->paper_template_path),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applicant_type' => ApplicantType::class,
            'sort_order' => 'integer',
            'finalists_confirmed_at' => 'datetime',
            'awards_confirmed_at' => 'datetime',
        ];
    }
}
