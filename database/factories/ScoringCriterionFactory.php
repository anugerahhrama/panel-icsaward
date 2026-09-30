<?php

namespace Database\Factories;

use App\Models\AssessmentTemplate;
use App\Models\ScoringCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoringCriterion>
 */
class ScoringCriterionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_template_id' => AssessmentTemplate::factory(),
            'aspect' => fake()->words(3, true),
            'criteria' => fake()->words(4, true),
            'description' => fake()->sentence(),
            'weight' => 20,
            'sort_order' => 1,
        ];
    }
}
