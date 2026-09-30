<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\SubmitPaper;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedSubmissionFileController extends Controller
{
    /**
     * Serve a submitted file inline to an external viewer (Office Online) through a short-lived signed URL.
     *
     * The route has no session auth, the signature is the authorization: only the admin preview
     * endpoint, after `downloadFiles`, hands these URLs out.
     *
     * @param  'paper'|'statement'  $file
     */
    public function __invoke(Submission $submission, string $file): StreamedResponse
    {
        $submitted = $submission->submittedFile($file);
        $disk = Storage::disk(SubmitPaper::DISK);

        abort_if($submitted === null || ! $disk->exists($submitted['path']), 404);

        return $disk->response($submitted['path'], $submitted['name'], [], 'inline');
    }
}
