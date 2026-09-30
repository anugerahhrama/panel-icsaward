<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PitchingSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The time a finalist pitches within their category's pitching session.
 *
 * @property int $id
 * @property int $pitching_session_id
 * @property int $submission_id
 * @property CarbonImmutable $starts_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['pitching_session_id', 'submission_id', 'starts_at'])]
class PitchingSlot extends Model
{
    /** @use HasFactory<PitchingSlotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PitchingSession, $this>
     */
    public function pitchingSession(): BelongsTo
    {
        return $this->belongsTo(PitchingSession::class);
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
        ];
    }
}
