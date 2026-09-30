<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\SubmitPaper;
use App\Http\Requests\StorePaperRequest;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;

class SubmissionPaperController extends Controller
{
    /**
     * Upload the paper (and Statement Letter) and lock the submission for review.
     */
    public function __invoke(StorePaperRequest $request, Submission $submission, SubmitPaper $submitPaper): RedirectResponse
    {
        $submitPaper->handle($submission, $request->file('paper'), $request->file('statement_letter'));

        return to_route('submissions.show', $submission);
    }
}
