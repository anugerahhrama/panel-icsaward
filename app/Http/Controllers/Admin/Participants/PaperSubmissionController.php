<?php

namespace App\Http\Controllers\Admin\Participants;

use App\Enums\FilePreviewKind;
use App\Enums\SubmissionStatus;
use App\Exports\PaperSubmissionsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ParticipantFilterRequest;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaperSubmissionController extends Controller
{
    private const string DEFAULT_SORT = '-paper_uploaded_at';

    /**
     * List the submissions whose paper has been uploaded, filtered, sorted and paginated on the server.
     */
    public function index(ParticipantFilterRequest $request): Response
    {
        $filters = $request->filters(self::DEFAULT_SORT);

        return Inertia::render('admin/participants/papers', [
            'submissions' => Submission::query()
                ->whereNotNull('paper_uploaded_at')
                ->filteredForAdmin($filters)
                ->with('reviewer:id,name')
                ->paginate($filters['per_page'])
                ->withQueryString()
                ->through(fn (Submission $submission): array => [
                    'id' => $submission->id,
                    'uuid' => $submission->uuid,
                    'paper_uploaded_at' => $submission->paper_uploaded_at?->toIso8601String(),
                    'name' => $submission->user->name,
                    'email' => $submission->user->email,
                    'company_name' => $submission->user->company_name,
                    'category' => $submission->awardCategory->name,
                    'initiative_title' => $submission->initiative_title,
                    'paper' => $submission->paper_path === null ? null : [
                        'name' => $submission->paper_original_name,
                        'url' => route('admin.participants.files.show', [$submission, 'paper']),
                        'preview_url' => route('admin.participants.files.preview', [$submission, 'paper']),
                        'preview_kind' => FilePreviewKind::fromFileName($submission->paper_original_name)->value,
                    ],
                    'statement' => $submission->statement_path === null ? null : [
                        'name' => $submission->statement_original_name,
                        'url' => route('admin.participants.files.show', [$submission, 'statement']),
                        'preview_url' => route('admin.participants.files.preview', [$submission, 'statement']),
                        'preview_kind' => FilePreviewKind::fromFileName($submission->statement_original_name)->value,
                    ],
                    'status' => $submission->status->value,
                    'verification' => [
                        'revision_note' => $submission->revision_note,
                        'revision_deadline' => $submission->revision_deadline?->toIso8601String(),
                        'disqualified_reason' => $submission->disqualified_reason,
                        'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
                        'reviewer' => $submission->reviewer?->name,
                        'notified_at' => $submission->notified_at?->toIso8601String(),
                    ],
                ]),
            'filters' => $filters,
            'categories' => AwardCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(SubmissionStatus::cases(), 'value'),
        ]);
    }

    /**
     * Download the paper submissions matching the table filters as an Excel file.
     */
    public function export(ParticipantFilterRequest $request): BinaryFileResponse
    {
        $timestamp = now(Setting::EVENT_TIMEZONE)->format('Ymd-Hi');

        return Excel::download(new PaperSubmissionsExport($request->filters(self::DEFAULT_SORT)), "paper-submissions-{$timestamp}.xlsx");
    }
}
