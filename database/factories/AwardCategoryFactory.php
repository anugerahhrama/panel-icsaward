<?php

namespace Database\Factories;

use App\Enums\ApplicantType;
use App\Models\AwardCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AwardCategory>
 */
class AwardCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Best '.fake()->unique()->word().' Initiative',
            'description' => fake()->sentence(),
            'applicant_type' => ApplicantType::Organization,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }

    /**
     * Indicate that the category is entered by individuals.
     */
    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'applicant_type' => ApplicantType::Individual,
        ]);
    }

    /**
     * Indicate that the committee confirmed the category's finalists.
     */
    public function finalistsConfirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'finalists_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the committee confirmed the category's awards.
     */
    public function awardsConfirmed(): static
    {
        return $this->finalistsConfirmed()->state(fn (array $attributes) => [
            'awards_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the committee announced the category's finalists to participants.
     */
    public function finalistsAnnounced(): static
    {
        return $this->finalistsConfirmed()->state(fn (array $attributes) => [
            'finalists_announced_at' => now(),
        ]);
    }

    /**
     * Indicate that the committee sent the Awarding Night invitations of the category.
     */
    public function invitationsSent(): static
    {
        return $this->finalistsAnnounced()->state(fn (array $attributes) => [
            'invitations_sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the committee announced the category's winners to participants.
     */
    public function winnersAnnounced(): static
    {
        return $this->invitationsSent()->awardsConfirmed()->state(fn (array $attributes) => [
            'winners_announced_at' => now(),
        ]);
    }
}
