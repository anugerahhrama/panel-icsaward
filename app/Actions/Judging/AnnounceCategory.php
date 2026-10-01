<?php

namespace App\Actions\Judging;

use App\Enums\Announcement;
use App\Jobs\SendAnnouncement;
use App\Models\AwardCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnounceCategory
{
    /**
     * Make an announcement for a category: from now on participants see the result, and each recipient is emailed.
     *
     * An announcement is final and made once. The category is claimed with a conditional update, so two admins
     * announcing at once cannot both email the recipients. Emails are queued after commit and each job claims the
     * recipient's notified column, so a retry never sends twice.
     *
     * @throws ValidationException
     */
    public function handle(AwardCategory $category, Announcement $announcement, User $announcer): int
    {
        return DB::transaction(function () use ($category, $announcement, $announcer): int {
            $category = AwardCategory::query()->lockForUpdate()->findOrFail($category->id);

            $reason = $announcement->blockedReason($category);

            if ($reason !== null) {
                throw ValidationException::withMessages(['announcement' => $reason]);
            }

            $column = $announcement->categoryColumn();

            $claimed = AwardCategory::query()
                ->whereKey($category->id)
                ->whereNull($column)
                ->update([
                    $column => now(),
                    $announcement->announcerColumn() => $announcer->id,
                ]);

            if ($claimed === 0) {
                throw ValidationException::withMessages([
                    'announcement' => "{$announcement->label()} for {$category->name} were already announced.",
                ]);
            }

            $recipients = $announcement->recipients($category)->get();

            Submission::query()
                ->whereKey($recipients->modelKeys())
                ->update([$announcement->notifiedColumn() => null]);

            foreach ($recipients as $submission) {
                SendAnnouncement::dispatch($submission, $announcement)->afterCommit();
            }

            return $recipients->count();
        });
    }
}
