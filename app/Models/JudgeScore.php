<?php

namespace App\Models;

use App\Enums\JudgingStage;
use Carbon\CarbonImmutable;
use Database\Factories\JudgeScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One judge's raw score (0–100) for one scoring criterion of one submission in one stage.
 *
 * @property int $id
 * @property int $submission_id
 * @property int $judge_id
 * @property int $scoring_criterion_id
 * @property JudgingStage $stage
 * @property int|null $raw_score
 * @property string|null $notes
 * @property CarbonImmutable|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['submission_id', 'judge_id', 'scoring_criterion_id', 'stage', 'raw_score', 'notes', 'submitted_at'])]
class JudgeScore extends Model
{
    /** @use HasFactory<JudgeScoreFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return BelongsTo<Judge, $this>
     */
    public function judge(): BelongsTo
    {
        return $this->belongsTo(Judge::class);
    }

    /**
     * @return BelongsTo<ScoringCriterion, $this>
     */
    public function scoringCriterion(): BelongsTo
    {
        return $this->belongsTo(ScoringCriterion::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => JudgingStage::class,
            'raw_score' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }
}
