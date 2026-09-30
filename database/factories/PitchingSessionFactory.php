<?php

namespace Database\Factories;

use App\Models\AwardCategory;
use App\Models\PitchingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PitchingSession>
 */
class PitchingSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'award_category_id' => AwardCategory::factory()->finalistsConfirmed(),
            'scheduled_at' => now()->addMonth()->startOfHour(),
            'location' => fake()->streetAddress(),
            'meeting_link' => fake()->url(),
        ];
    }
}
