<?php

namespace App\Http\Controllers\Admin\Participants;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Exports\RegistrationsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ParticipantFilterRequest;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrationController extends Controller
{
    private const string DEFAULT_SORT = '-created_at';

    /**
     * List every registration, filtered, sorted and paginated on the server.
     *
     * The personal submission link is only sent to superadmins: opening it marks the participant's email as verified.
     */
    public function index(ParticipantFilterRequest $request): Response
    {
        $filters = $request->filters(self::DEFAULT_SORT);
        $canSeeSubmissionLink = $request->user()?->role === UserRole::Superadmin;

        return Inertia::render('admin/participants/registrations', [
            'submissions' => Submission::query()
                ->filteredForAdmin($filters)
                ->paginate($filters['per_page'])
                ->withQueryString()
                ->through(fn (Submission $submission): array => [
                    'id' => $submission->id,
                    'uuid' => $submission->uuid,
                    'registered_at' => $submission->created_at?->toIso8601String(),
                    'name' => $submission->user->name,
                    'email' => $submission->user->email,
                    'email_verified' => $submission->user->email_verified_at !== null,
                    'phone' => $submission->user->phone,
                    'position' => $submission->user->position,
                    'company_name' => $submission->user->company_name,
                    'category' => $submission->awardCategory->name,
                    'applicant_type' => $submission->awardCategory->applicant_type->value,
                    'initiative_title' => $submission->initiative_title,
                    'initiative_description' => $submission->initiative_description,
                    'status' => $submission->status->value,
                    'paper_uploaded_at' => $submission->paper_uploaded_at?->toIso8601String(),
                    'confirmation_sent_at' => $submission->confirmation_sent_at?->toIso8601String(),
                    'submission_url' => $canSeeSubmissionLink ? route('submissions.show', $submission) : null,
                ]),
            'filters' => $filters,
            'categories' => AwardCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(SubmissionStatus::cases(), 'value'),
        ]);
    }

    /**
     * Download the registrations matching the table filters as an Excel file.
     */
    public function export(ParticipantFilterRequest $request): BinaryFileResponse
    {
        $timestamp = now(Setting::EVENT_TIMEZONE)->format('Ymd-Hi');

        return Excel::download(new RegistrationsExport($request->filters(self::DEFAULT_SORT)), "registrations-{$timestamp}.xlsx");
    }
}
