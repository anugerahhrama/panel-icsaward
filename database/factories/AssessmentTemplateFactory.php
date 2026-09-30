<?php

namespace Database\Factories;

use App\Models\AssessmentTemplate;
use App\Models\ScoringCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentTemplate>
 */
class AssessmentTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Assessment',
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Give the template five scoring criteria whose weights add up to 100.
     */
    public function withCriteria(): static
    {
        return $this->has(
            ScoringCriterion::factory()
                ->count(5)
                ->sequence(fn ($sequence) => [
                    'weight' => [15, 20, 35, 15, 15][$sequence->index],
                    'sort_order' => $sequence->index + 1,
                ]),
            'criteria',
        );
    }
}
