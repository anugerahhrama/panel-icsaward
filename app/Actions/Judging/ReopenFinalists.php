<?php

namespace App\Actions\Judging;

use App\Enums\Announcement;
use App\Enums\JudgingStage;
use App\Enums\SubmissionStatus;
use App\Models\AwardCategory;
use App\Models\JudgeScore;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenFinalists
{
    /**
     * Undo a category's finalist confirmation: its finalists go back to `qualified` and its results are no longer frozen.
     *
     * Refused once the finalists are announced, since participants have already been told, or once a finalist has
     * pitching scores, since those belong to the confirmed selection. The finalists'
     * pitching slots are removed; the category's pitching session stays for the next selection.
     *
     * @throws ValidationException
     */
    public function handle(AwardCategory $category): int
    {
        return DB::transaction(function () use ($category): int {
            $category = AwardCategory::query()->lockForUpdate()->findOrFail($category->id);

            if (! $category->isFinalistsConfirmed()) {
                throw ValidationException::withMessages([
                    'finalists' => 'The finalists for this category are not confirmed.',
                ]);
            }

            if ($category->isAnnounced(Announcement::Finalists)) {
                throw ValidationException::withMessages([
                    'finalists' => 'The finalists of this category are already announced, so they cannot be reopened.',
                ]);
            }

            $hasPitchingScores = JudgeScore::query()
                ->where('stage', JudgingStage::Pitching)
                ->whereIn('submission_id', $category->finalists()->select('id'))
                ->exists();

            if ($hasPitchingScores) {
                throw ValidationException::withMessages([
                    'finalists' => 'Judges have already scored these finalists in pitching, so they cannot be reopened.',
                ]);
            }

            PitchingSlot::query()
                ->whereIn('pitching_session_id', PitchingSession::query()->where('award_category_id', $category->id)->select('id'))
                ->delete();

            $reopened = Submission::query()
                ->where('award_category_id', $category->id)
                ->where('status', SubmissionStatus::Finalist)
                ->update(['status' => SubmissionStatus::Qualified]);

            $category->forceFill([
                'finalists_confirmed_at' => null,
                'finalists_confirmed_by' => null,
            ])->save();

            return $reopened;
        });
    }
}
