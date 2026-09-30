<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PitchingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A category's pitching session; its finalists pitch in the time slots of the session.
 *
 * @property int $id
 * @property int $award_category_id
 * @property CarbonImmutable $scheduled_at
 * @property string|null $location
 * @property string|null $meeting_link
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['award_category_id', 'scheduled_at', 'location', 'meeting_link'])]
class PitchingSession extends Model
{
    /** @use HasFactory<PitchingSessionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AwardCategory, $this>
     */
    public function awardCategory(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class);
    }

    /**
     * The finalists' time slots, earliest first.
     *
     * @return HasMany<PitchingSlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(PitchingSlot::class)->orderBy('starts_at')->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }
}
