<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\SubmitPaper;
use App\Models\Submission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionFileController extends Controller
{
    /**
     * Download a file the participant submitted, from the private disk, under its original name.
     *
     * @param  'paper'|'statement'  $file
     */
    public function __invoke(Submission $submission, string $file): StreamedResponse
    {
        Gate::authorize('downloadFiles', $submission);

        $submitted = $submission->submittedFile($file);
        $disk = Storage::disk(SubmitPaper::DISK);

        abort_if($submitted === null || ! $disk->exists($submitted['path']), 404);

        return $disk->download($submitted['path'], $submitted['name']);
    }
}
