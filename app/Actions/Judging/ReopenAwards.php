<?php

namespace App\Actions\Judging;

use App\Enums\Announcement;
use App\Models\AwardCategory;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenAwards
{
    /**
     * Undo a category's award confirmation: its awards are cleared and its Stage 2 and final results are no longer frozen.
     *
     * Refused once the winners are announced, since participants have already been told their award.
     *
     * @throws ValidationException
     */
    public function handle(AwardCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            $category = AwardCategory::query()->lockForUpdate()->findOrFail($category->id);

            if (! $category->isAwardsConfirmed()) {
                throw ValidationException::withMessages([
                    'awards' => 'The awards for this category are not confirmed.',
                ]);
            }

            if ($category->isAnnounced(Announcement::Winners)) {
                throw ValidationException::withMessages([
                    'awards' => 'The winners of this category are already announced, so the awards cannot be reopened.',
                ]);
            }

            Submission::query()->where('award_category_id', $category->id)->update(['award' => null]);

            $category->forceFill([
                'awards_confirmed_at' => null,
                'awards_confirmed_by' => null,
            ])->save();
        });
    }
}
