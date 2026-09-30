<?php

namespace App\Models;

use Database\Factories\AssessmentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description'])]
class AssessmentTemplate extends Model
{
    /** @use HasFactory<AssessmentTemplateFactory> */
    use HasFactory;

    /**
     * The scoring criteria of this template, in display order.
     *
     * @return HasMany<ScoringCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(ScoringCriterion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<AwardCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(AwardCategory::class);
    }
}
