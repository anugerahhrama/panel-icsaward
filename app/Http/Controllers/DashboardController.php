<?php

namespace App\Http\Controllers;

use App\Enums\Announcement;
use App\Enums\SubmissionStatus;
use App\Enums\TimelineStage;
use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the participant dashboard, or send staff to their own area.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (in_array($user->role, [UserRole::Superadmin, UserRole::Admin], true)) {
            return to_route('admin.dashboard');
        }

        if ($user->role === UserRole::Judge) {
            return to_route('judge.dashboard');
        }

        $deadline = Setting::endOfDay('paper_deadline');
        $defaultPaperTemplateUrl = Setting::publicFileUrl('submission_template_path');
        $awardingNight = Setting::get('timeline_awarding_night') ?: null;

        return Inertia::render('dashboard', [
            'submissions' => $user->submissions()
                ->with(['awardCategory.pitchingSession', 'pitchingSlot'])
                ->orderBy('id')
                ->get()
                ->map(fn (Submission $submission): array => [
                    'uuid' => $submission->uuid,
                    'initiativeTitle' => $submission->initiative_title,
                    'category' => $submission->awardCategory->name,
                    'paperTemplateUrl' => $submission->awardCategory->paper_template_url ?? $defaultPaperTemplateUrl,
                    'status' => $submission->statusForParticipant()->value,
                    'revisionNote' => $submission->revision_note,
                    'revisionDeadline' => $submission->revision_deadline?->toIso8601String(),
                    'isRevisionOpen' => $submission->isRevisionOpen(),
                    'disqualifiedReason' => $submission->disqualified_reason,
                    'paperUploadedAt' => $submission->paper_uploaded_at?->toIso8601String(),
                    'paperOriginalName' => $submission->paper_original_name,
                    'paperUrl' => $submission->paper_path === null
                        ? null
                        : route('submissions.files.show', [$submission, 'paper']),
                    'statementOriginalName' => $submission->statement_original_name,
                    'statementUrl' => $submission->statement_path === null
                        ? null
                        : route('submissions.files.show', [$submission, 'statement']),
                    'pitching' => $this->pitching($submission),
                    'notSelected' => $submission->status === SubmissionStatus::Qualified
                        && $submission->awardCategory->isAnnounced(Announcement::Finalists),
                    'awardingNight' => $this->isAnnouncedFinalist($submission, Announcement::Invitations)
                        ? ['details' => $awardingNight]
                        : null,
                    'award' => $this->isAnnouncedFinalist($submission, Announcement::Winners)
                        ? $submission->award?->value
                        : null,
                ]),
            'paperDeadline' => $deadline?->toIso8601String(),
            'isClosed' => $deadline !== null && now()->greaterThan($deadline),
            'statementLetterTemplateUrl' => Setting::publicFileUrl('statement_letter_template_path'),
            'contactEmail' => Setting::get('contact_email'),
            'timeline' => TimelineStage::forParticipants(),
        ]);
    }

    /**
     * Whether the submission is a finalist and the committee has made this announcement for its category.
     */
    private function isAnnouncedFinalist(Submission $submission, Announcement $announcement): bool
    {
        return $submission->status === SubmissionStatus::Finalist && $submission->awardCategory->isAnnounced($announcement);
    }

    /**
     * The finalist's pitching schedule, once the finalists are announced and the committee has set up the session.
     *
     * @return array{scheduledAt: string, startsAt: string|null, location: string|null, meetingLink: string|null}|null
     */
    private function pitching(Submission $submission): ?array
    {
        $session = $submission->awardCategory->pitchingSession;

        if (! $this->isAnnouncedFinalist($submission, Announcement::Finalists) || $session === null) {
            return null;
        }

        return [
            'scheduledAt' => $session->scheduled_at->toIso8601String(),
            'startsAt' => $submission->pitchingSlot?->starts_at->toIso8601String(),
            'location' => $session->location,
            'meetingLink' => $session->meeting_link,
        ];
    }
}
