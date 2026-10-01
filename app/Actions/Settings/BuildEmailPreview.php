<?php

namespace App\Actions\Settings;

use App\Enums\Announcement;
use App\Enums\Award;
use App\Enums\SubmissionStatus;
use App\Mail\CategoryAnnouncement;
use App\Mail\SubmissionConfirmation;
use App\Mail\VerificationDecision;
use App\Models\AwardCategory;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BuildEmailPreview
{
    /**
     * Build the real mailable for an email template, filled with sample data that is never saved.
     *
     * @param  array{subject: string, body: string, contact_email: string}  $template  The unsaved template from the form.
     *
     * @throws InvalidArgumentException
     */
    public function handle(string $templateKey, array $template): SubmissionConfirmation|VerificationDecision|CategoryAnnouncement
    {
        $submission = $this->sampleSubmission($templateKey);

        return match ($templateKey) {
            'confirmation' => new SubmissionConfirmation($submission, $template),
            'qualified', 'needs_revision', 'disqualified' => new VerificationDecision($submission, $template),
            'finalist_announcement' => new CategoryAnnouncement($submission, Announcement::Finalists, $template),
            'awarding_invitation' => new CategoryAnnouncement($submission, Announcement::Invitations, $template),
            'winner_announcement' => new CategoryAnnouncement($submission, Announcement::Winners, $template),
            default => throw new InvalidArgumentException("Unknown email template [{$templateKey}]."),
        };
    }

    /**
     * An in-memory submission with its relations set, so rendering the mailable runs no extra queries.
     */
    private function sampleSubmission(string $templateKey): Submission
    {
        $pitchingDay = now(Setting::EVENT_TIMEZONE)->addWeeks(2)->setTime(9, 0);

        $category = new AwardCategory([
            'name' => AwardCategory::query()->orderBy('sort_order')->value('name') ?? 'Sample Category',
        ]);
        $category->setRelation('pitchingSession', new PitchingSession([
            'scheduled_at' => $pitchingDay,
            'location' => 'Jakarta Convention Center',
            'meeting_link' => 'https://meet.example.com/ics-award-pitching',
        ]));

        $submission = (new Submission([
            'initiative_title' => 'Community Mangrove Restoration Program',
            'status' => match ($templateKey) {
                'confirmation' => SubmissionStatus::Registered,
                'needs_revision' => SubmissionStatus::NeedsRevision,
                'disqualified' => SubmissionStatus::Disqualified,
                'qualified' => SubmissionStatus::Qualified,
                default => SubmissionStatus::Finalist,
            },
            'revision_note' => 'Please add the measurable impact of your initiative in section 3.',
            'revision_deadline' => now()->addWeek(),
            'disqualified_reason' => 'The initiative started after the eligibility period.',
        ]))->forceFill([
            'uuid' => (string) Str::uuid(),
            'award' => Award::Gold,
        ]);

        return $submission
            ->setRelation('user', new User(['name' => 'Jane Doe', 'email' => 'jane.doe@example.com']))
            ->setRelation('awardCategory', $category)
            ->setRelation('pitchingSlot', new PitchingSlot(['starts_at' => $pitchingDay->addHour()]));
    }
}
