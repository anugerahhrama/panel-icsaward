<?php

namespace App\Http\Controllers\Admin\Participants;

use App\Actions\Submissions\VerifySubmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifySubmissionRequest;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VerificationController extends Controller
{
    /**
     * Record the administrative verification decision for a submission under review.
     */
    public function update(VerifySubmissionRequest $request, Submission $submission, VerifySubmission $verifySubmission): RedirectResponse
    {
        $decision = $request->decision();

        $verifySubmission->handle($submission, $request->user(), $decision, $request->details());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Submission marked as {$decision->label()}."]);

        return back();
    }
}
