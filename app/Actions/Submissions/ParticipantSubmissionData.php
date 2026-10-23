<?php

namespace App\Actions\Submissions;

use App\Enums\Announcement;
use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Support\Collection;

/**
 * @phpstan-type PitchingSchedule array{scheduledAt: string, startsAt: string|null, location: string|null, meetingLink: string|null}
 * @phpstan-type ParticipantSubmission array{
 *     uuid: string,
 *     initiativeTitle: string,
 *     category: string,
 *     paperTemplateUrl: string|null,
 *     status: string,
 *     revisionNote: string|null,
 *     revisionDeadline: string|null,
 *     isRevisionOpen: bool,
 *     disqualifiedReason: string|null,
 *     paperUploadedAt: string|null,
 *     paperOriginalName: string|null,
 *     paperUrl: string|null,
 *     statementOriginalName: string|null,
 *     statementUrl: string|null,
 *     pitching: PitchingSchedule|null,
 *     notSelected: bool,
 *     awardingNight: array{details: string|null}|null,
 *     award: string|null,
 * }
 */
class ParticipantSubmissionData
{
    /**
     * The relations every submission needs before it is shown to its participant.
     *
     * @var list<string>
     */
    public const array RELATIONS = ['awardCategory.pitchingSession', 'pitchingSlot'];

    /**
     * Describe the participant's submissions as shown on the dashboard and the submission page.
     *
     * @param  Collection<int, Submission>  $submissions
     * @return Collection<int, ParticipantSubmission>
     */
    public function collection(Collection $submissions): Collection
    {
        $defaultPaperTemplateUrl = Setting::publicFileUrl('submission_template_path');
        $awardingNight = Setting::get('timeline_awarding_night') ?: null;

        return $submissions->map(fn (Submission $submission): array => [
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
        ]);
    }

    /**
     * Describe a single submission for its participant.
     *
     * @return ParticipantSubmission
     */
    public function one(Submission $submission): array
    {
        $submission->loadMissing(self::RELATIONS);

        /** @var ParticipantSubmission */
        return $this->collection(collect([$submission]))->first();
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
     * @return PitchingSchedule|null
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
