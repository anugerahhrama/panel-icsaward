<?php

namespace App\Http\Controllers;

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
    public function show(Request $request, Submission $submission): Response
    {
        Gate::authorize('view', $submission);

        $user = $request->user();

        if ($user !== null && ! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $deadline = Setting::endOfDay('paper_deadline');

        return Inertia::render('submissions/show', [
            'submission' => [
                'uuid' => $submission->uuid,
                'initiativeTitle' => $submission->initiative_title,
                'category' => $submission->awardCategory->name,
                'paperTemplateUrl' => $submission->awardCategory->paper_template_url ?? Setting::publicFileUrl('submission_template_path'),
                'status' => $submission->statusForParticipant()->value,
                'paperUploadedAt' => $submission->paper_uploaded_at?->toIso8601String(),
                'paperOriginalName' => $submission->paper_original_name,
                'paperUrl' => $submission->paper_path === null
                    ? null
                    : route('submissions.files.show', [$submission, 'paper']),
                'statementOriginalName' => $submission->statement_original_name,
                'statementUrl' => $submission->statement_path === null
                    ? null
                    : route('submissions.files.show', [$submission, 'statement']),
                'revisionNote' => $submission->revision_note,
                'revisionDeadline' => $submission->revision_deadline?->toIso8601String(),
                'isRevisionOpen' => $submission->isRevisionOpen(),
            ],
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
