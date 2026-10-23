<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\RegisterSubmission;
use App\Actions\Submissions\RegistrationFormData;
use App\Actions\Submissions\SendSubmissionConfirmation;
use App\Enums\RegistrationStatus;
use App\Http\Requests\RegisterInitiativeRequest;
use App\Models\AwardCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionRegistrationController extends Controller
{
    /**
     * Show the Initiative → Terms form for a participant registering another initiative.
     */
    public function create(Request $request, RegistrationFormData $formData): Response|RedirectResponse
    {
        $user = $request->user();

        if (! RegisterSubmission::canRegisterAnother($user)) {
            $status = RegistrationStatus::current();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $status->isOpen() ? RegisterSubmission::limitMessage() : $status->rejectionMessage(),
            ]);

            return to_route('dashboard');
        }

        return Inertia::render('submissions/create', [
            'categories' => $formData->categories(),
            'terms' => $formData->terms(),
            'registeredCategoryIds' => $user->submissions()->pluck('award_category_id'),
        ]);
    }

    /**
     * Validate the "Initiative" step before the participant moves on to the terms.
     */
    public function validateInitiative(RegisterInitiativeRequest $request): HttpResponse
    {
        return response()->noContent();
    }

    /**
     * Register the initiative and email its personal submission link.
     */
    public function store(
        RegisterInitiativeRequest $request,
        RegisterSubmission $registerSubmission,
        SendSubmissionConfirmation $sendSubmissionConfirmation,
    ): RedirectResponse {
        $submission = $registerSubmission->handle(
            $request->user(),
            AwardCategory::query()->whereKey($request->integer('award_category_id'))->firstOrFail(),
            $request->initiative(),
        );

        $sendSubmissionConfirmation->handle($submission);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Initiative registered.']);

        return to_route('submissions.show', $submission);
    }
}
