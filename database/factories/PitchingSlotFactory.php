<?php

namespace Database\Factories;

use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PitchingSlot>
 */
class PitchingSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pitching_session_id' => PitchingSession::factory(),
            'submission_id' => Submission::factory()->finalist(),
            'starts_at' => now()->addMonth()->startOfHour(),
        ];
    }
}
