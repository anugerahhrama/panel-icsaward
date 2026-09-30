<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'award_category_id' => AwardCategory::factory(),
            'initiative_title' => fake()->sentence(4),
            'initiative_description' => fake()->paragraph(),
            'status' => SubmissionStatus::Registered,
            'terms_accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the paper has been submitted and locked.
     */
    public function paperSubmitted(): static
    {
        return $this->state(fn (): array => [
            'status' => SubmissionStatus::UnderReview,
            'paper_path' => 'submissions/paper.pdf',
            'paper_original_name' => 'paper.pdf',
            'statement_path' => 'submissions/statement.pdf',
            'statement_original_name' => 'statement.pdf',
            'paper_uploaded_at' => now(),
        ]);
    }

    /**
     * Indicate that the committee reopened the submitted files for a revision.
     */
    public function needsRevision(): static
    {
        return $this->paperSubmitted()->state(fn (): array => [
            'status' => SubmissionStatus::NeedsRevision,
            'revision_note' => 'Please use the official template.',
            'revision_deadline' => now()->addWeek(),
        ]);
    }

    /**
     * Indicate that the submission passed the administrative selection and is ready for desk evaluation.
     */
    public function qualified(): static
    {
        return $this->paperSubmitted()->state(fn (): array => [
            'status' => SubmissionStatus::Qualified,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the committee confirmed the submission as a finalist.
     */
    public function finalist(): static
    {
        return $this->qualified()->state(fn (): array => [
            'status' => SubmissionStatus::Finalist,
        ]);
    }
}
