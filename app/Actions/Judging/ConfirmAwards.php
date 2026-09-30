<?php

namespace App\Actions\Judging;

use App\Enums\Award;
use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmAwards
{
    /**
     * Confirm the committee's awards for a category and freeze its Stage 2 and final results, in one transaction.
     *
     * The final rank only suggests the awards; the committee decides, also on ties. Finalists left out of `$awards` get
     * no award. The category is claimed with a conditional update, so two admins confirming at once cannot both win,
     * and holding the Stage 2 recalculation lock keeps a running recalculation from rewriting the ranks underneath.
     *
     * @param  array<int, Award>  $awards  submission id => award
     *
     * @throws ValidationException
     */
    public function handle(AwardCategory $category, array $awards, User $confirmer): int
    {
        $lock = Cache::lock(CalculateStageTwoScores::LOCK_KEY, 60);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'awards' => 'Scores are being recalculated. Try again in a moment.',
            ]);
        }

        try {
            return DB::transaction(fn (): int => $this->confirm($category, $awards, $confirmer));
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<int, Award>  $awards
     */
    private function confirm(AwardCategory $category, array $awards, User $confirmer): int
    {
        if (JudgingStage::current(lock: true) === JudgingStage::Pitching) {
            throw ValidationException::withMessages([
                'awards' => 'Pitching is still open. Close it in Settings → Judging before confirming awards.',
            ]);
        }

        if ($awards === []) {
            throw ValidationException::withMessages([
                'awards' => 'Give at least one award.',
            ]);
        }

        foreach (Award::cases() as $award) {
            $given = count(array_filter($awards, fn (Award $value): bool => $value === $award));

            if ($given > $award->quota()) {
                throw ValidationException::withMessages([
                    'awards' => "A category can give at most {$award->quota()} {$award->label()}.",
                ]);
            }
        }

        $claimed = AwardCategory::query()
            ->whereKey($category->id)
            ->whereNotNull('finalists_confirmed_at')
            ->whereNull('awards_confirmed_at')
            ->update([
                'awards_confirmed_at' => now(),
                'awards_confirmed_by' => $confirmer->id,
            ]);

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'awards' => 'The awards for this category are already confirmed, or its finalists are not.',
            ]);
        }

        $eligible = Submission::query()
            ->whereKey(array_keys($awards))
            ->where('award_category_id', $category->id)
            ->where('status', SubmissionStatus::Finalist)
            ->whereNotNull('final_rank')
            ->lockForUpdate()
            ->count();

        if ($eligible !== count($awards)) {
            throw ValidationException::withMessages([
                'awards' => 'Only finalists of this category with a final score can receive an award. Reload the page and try again.',
            ]);
        }

        Submission::query()->where('award_category_id', $category->id)->update(['award' => null]);

        foreach ($awards as $submissionId => $award) {
            Submission::query()->whereKey($submissionId)->update(['award' => $award]);
        }

        return count($awards);
    }
}
