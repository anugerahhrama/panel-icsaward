<?php

namespace App\Actions\Submissions;

use App\Enums\UserRole;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteRegistration
{
    /**
     * Permanently delete a registration and its uploaded files.
     *
     * When it was the participant's last registration, the account is force deleted as well, so the
     * email can sign up again. A submission judges have scored is kept: deleting it would silently
     * remove those scores. Files are removed only after the deletion is committed.
     *
     * @throws ValidationException
     */
    public function handle(Submission $submission): void
    {
        DB::transaction(function () use ($submission): void {
            $locked = Submission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->judgeScores()->exists()) {
                throw ValidationException::withMessages([
                    'submission' => 'This registration has judge scores and cannot be deleted.',
                ]);
            }

            $user = $locked->user;

            $locked->delete();

            if ($user->role === UserRole::Participant && ! $user->submissions()->exists()) {
                $user->forceDelete();
            }
        });

        Storage::disk(SubmitPaper::DISK)->deleteDirectory("submissions/{$submission->uuid}");
    }
}
