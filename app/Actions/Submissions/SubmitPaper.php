<?php

namespace App\Actions\Submissions;

use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SubmitPaper
{
    /**
     * Files live on the private disk; they are only ever served through authorized routes.
     */
    public const string DISK = 'local';

    /**
     * Store the paper (and optional Statement Letter), then lock the submission for review.
     *
     * A first submission needs the paper and is bound by `paper_deadline`. A revision (status
     * `needs_revision`) is bound by `revision_deadline` instead and only replaces the files that were
     * uploaded again; the replaced files are removed once the change is committed.
     *
     * The lock and deadlines are re-checked inside the transaction on a locked row, so a double
     * submit or a request that arrives after the deadline cannot slip through. Files stored for
     * a rejected attempt are removed again.
     *
     * @throws ValidationException
     */
    public function handle(Submission $submission, ?UploadedFile $paper, ?UploadedFile $statementLetter): Submission
    {
        $directory = "submissions/{$submission->uuid}";
        $paperPath = $paper === null ? null : $this->store($paper, $directory);
        $statementPath = $statementLetter === null ? null : $this->store($statementLetter, $directory);

        try {
            [$submission, $replacedPaths] = DB::transaction(function () use ($submission, $paper, $paperPath, $statementLetter, $statementPath): array {
                $locked = Submission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

                $this->ensureCanSubmit($locked, $paper !== null);

                $replacedPaths = [];
                $changes = [
                    'paper_uploaded_at' => now(),
                    'status' => SubmissionStatus::UnderReview,
                ];

                if ($paper !== null) {
                    $replacedPaths[] = $locked->paper_path;
                    $changes['paper_path'] = $paperPath;
                    $changes['paper_original_name'] = $paper->getClientOriginalName();
                }

                if ($statementLetter !== null || $locked->paper_uploaded_at === null) {
                    $replacedPaths[] = $locked->statement_path;
                    $changes['statement_path'] = $statementPath;
                    $changes['statement_original_name'] = $statementLetter?->getClientOriginalName();
                }

                $locked->update($changes);

                return [$locked, array_values(array_filter($replacedPaths))];
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete(array_filter([$paperPath, $statementPath]));

            throw $exception;
        }

        Storage::disk(self::DISK)->delete($replacedPaths);

        return $submission;
    }

    private function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, self::DISK);

        if ($path === false) {
            throw new RuntimeException("Unable to store {$file->getClientOriginalName()}.");
        }

        return $path;
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanSubmit(Submission $submission, bool $hasPaper): void
    {
        if ($submission->status === SubmissionStatus::NeedsRevision && ! $submission->isRevisionOpen()) {
            throw ValidationException::withMessages([
                'paper' => 'The revision deadline has passed.',
            ]);
        }

        if ($submission->isPaperLocked()) {
            throw ValidationException::withMessages([
                'paper' => 'Your paper has already been submitted and can no longer be changed.',
            ]);
        }

        if ($submission->isRevisionOpen()) {
            return;
        }

        if (! $hasPaper) {
            throw ValidationException::withMessages([
                'paper' => 'The submission paper field is required.',
            ]);
        }

        $deadline = Setting::endOfDay('paper_deadline');

        if ($deadline !== null && now()->greaterThan($deadline)) {
            throw ValidationException::withMessages([
                'paper' => 'The submission deadline has passed.',
            ]);
        }
    }
}
