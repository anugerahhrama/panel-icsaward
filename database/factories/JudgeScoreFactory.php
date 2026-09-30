<?php

namespace Database\Factories;

use App\Enums\JudgingStage;
use App\Models\Judge;
use App\Models\JudgeScore;
use App\Models\ScoringCriterion;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JudgeScore>
 */
class JudgeScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'judge_id' => Judge::factory(),
            'scoring_criterion_id' => ScoringCriterion::factory(),
            'stage' => JudgingStage::DeskEvaluation,
            'raw_score' => fake()->numberBetween(0, 100),
            'notes' => null,
            'submitted_at' => null,
        ];
    }

    /**
     * The judge has submitted this score.
     */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'submitted_at' => now(),
        ]);
    }

    /**
     * The score was given in the pitching stage.
     */
    public function pitching(): static
    {
        return $this->state(fn (): array => [
            'stage' => JudgingStage::Pitching,
        ]);
    }
}
