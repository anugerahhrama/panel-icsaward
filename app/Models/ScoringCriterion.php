<?php

namespace App\Models;

use Database\Factories\ScoringCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_template_id
 * @property string $aspect
 * @property string $criteria
 * @property string|null $description
 * @property int $weight
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('scoring_criteria')]
#[Fillable(['aspect', 'criteria', 'description', 'weight', 'sort_order'])]
class ScoringCriterion extends Model
{
    /** @use HasFactory<ScoringCriterionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AssessmentTemplate, $this>
     */
    public function assessmentTemplate(): BelongsTo
    {
        return $this->belongsTo(AssessmentTemplate::class);
    }

    /**
     * The judge scores given on this criterion, across submissions and stages.
     *
     * @return HasMany<JudgeScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(JudgeScore::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
