<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\ParticipantSubmissionData;
use App\Enums\TimelineStage;
use App\Http\Requests\StorePaperRequest;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionController extends Controller
{
    /**
     * Show the personal submission page linked from the confirmation email.
     *
     * Opening the link proves the participant received the email, so it also verifies their address.
     */
    public function show(Request $request, Submission $submission, ParticipantSubmissionData $participantSubmissionData): Response
    {
        Gate::authorize('view', $submission);

        $user = $request->user();

        if ($user !== null && ! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $deadline = Setting::endOfDay('paper_deadline');

        return Inertia::render('submissions/show', [
            'submission' => $participantSubmissionData->one($submission),
            'requirements' => [
                'paperExtensions' => StorePaperRequest::paperExtensions(),
                'statementLetterExtensions' => StorePaperRequest::STATEMENT_LETTER_EXTENSIONS,
                'maxSizeMb' => StorePaperRequest::maxSizeMb(),
                'requireStatementLetter' => StorePaperRequest::requiresStatementLetter(),
            ],
            'paperDeadline' => $deadline?->toIso8601String(),
            'isClosed' => $deadline !== null && now()->greaterThan($deadline),
            'statementLetterTemplateUrl' => Setting::publicFileUrl('statement_letter_template_path'),
            'contactEmail' => Setting::get('contact_email'),
            'timeline' => TimelineStage::forParticipants(),
        ]);
    }
}
