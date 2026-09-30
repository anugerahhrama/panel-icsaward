<?php

namespace Database\Factories;

use App\Models\Judge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Judge>
 */
class JudgeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'position' => fake()->jobTitle(),
            'institution' => fake()->company(),
            'bio' => fake()->paragraph(),
            'show_on_landing' => false,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    /**
     * Indicate that the judge has a login account whose password the admin set.
     */
    public function withAccount(string $password = 'password'): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->judge()->state(['password' => $password]),
            'account_password' => $password,
        ]);
    }

    /**
     * Assign the judge to score the given categories.
     *
     * @param  list<int>  $categoryIds
     */
    public function assignedTo(array $categoryIds, bool $recused = false): static
    {
        return $this->afterCreating(fn (Judge $judge) => $judge->categories()->attach(
            collect($categoryIds)->mapWithKeys(fn (int $id): array => [$id => ['is_recused' => $recused]])->all(),
        ));
    }
}
