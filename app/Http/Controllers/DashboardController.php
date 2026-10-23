<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\ParticipantSubmissionData;
use App\Actions\Submissions\RegisterSubmission;
use App\Enums\TimelineStage;
use App\Enums\UserRole;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the participant dashboard, or send staff to their own area.
     */
    public function __invoke(Request $request, ParticipantSubmissionData $participantSubmissionData): Response|RedirectResponse
    {
        $user = $request->user();

        if (in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true)) {
            return to_route('admin.dashboard');
        }

        if ($user->role === UserRole::Judge) {
            return to_route('judge.dashboard');
        }

        $deadline = Setting::endOfDay('paper_deadline');

        return Inertia::render('dashboard', [
            'submissions' => $participantSubmissionData->collection(
                $user->submissions()->with(ParticipantSubmissionData::RELATIONS)->orderBy('id')->get(),
            ),
            'paperDeadline' => $deadline?->toIso8601String(),
            'isClosed' => $deadline !== null && now()->greaterThan($deadline),
            'statementLetterTemplateUrl' => Setting::publicFileUrl('statement_letter_template_path'),
            'contactEmail' => Setting::get('contact_email'),
            'timeline' => TimelineStage::forParticipants(),
            'canRegisterAnother' => RegisterSubmission::canRegisterAnother($user),
        ]);
    }
}
