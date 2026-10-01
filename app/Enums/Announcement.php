<?php

namespace App\Enums;

use App\Models\AwardCategory;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The committee's announcements per category, made in this order: finalists, Awarding Night invitations, winners.
 */
enum Announcement: string
{
    case Finalists = 'finalists';
    case Invitations = 'invitations';
    case Winners = 'winners';

    /**
     * The `award_categories` timestamp recording that the announcement was made.
     */
    public function categoryColumn(): string
    {
        return match ($this) {
            self::Finalists => 'finalists_announced_at',
            self::Invitations => 'invitations_sent_at',
            self::Winners => 'winners_announced_at',
        };
    }

    /**
     * The `award_categories` column holding the admin who made the announcement.
     */
    public function announcerColumn(): string
    {
        return match ($this) {
            self::Finalists => 'finalists_announced_by',
            self::Invitations => 'invitations_sent_by',
            self::Winners => 'winners_announced_by',
        };
    }

    /**
     * The `submissions` timestamp claimed by the job that emails a recipient.
     */
    public function notifiedColumn(): string
    {
        return match ($this) {
            self::Finalists => 'finalist_notified_at',
            self::Invitations => 'invitation_notified_at',
            self::Winners => 'award_notified_at',
        };
    }

    /**
     * The settings key prefix of the email template (`<key>_email_subject` / `<key>_email_body`).
     */
    public function templateKey(): string
    {
        return match ($this) {
            self::Finalists => 'finalist_announcement',
            self::Invitations => 'awarding_invitation',
            self::Winners => 'winner_announcement',
        };
    }

    /**
     * Human readable label, matching `ANNOUNCEMENT_LABELS` on the frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::Finalists => 'Finalists',
            self::Invitations => 'Awarding Night invitations',
            self::Winners => 'Winners',
        };
    }

    /**
     * The submissions that receive this announcement: the finalists, or for winners only the award recipients.
     *
     * @return HasMany<Submission, AwardCategory>
     */
    public function recipients(AwardCategory $category): HasMany
    {
        return match ($this) {
            self::Finalists, self::Invitations => $category->finalists(),
            self::Winners => $category->finalists()->whereNotNull('award'),
        };
    }

    /**
     * Why the category is not ready for this announcement yet, or null when it is.
     */
    public function blockedReason(AwardCategory $category): ?string
    {
        return match ($this) {
            self::Finalists => $category->isFinalistsConfirmed() ? null : 'Confirm the finalists in Score Recap first.',
            self::Invitations => $category->isAnnounced(self::Finalists) ? null : 'Announce the finalists first.',
            self::Winners => match (true) {
                ! $category->isAwardsConfirmed() => 'Confirm the awards in Score Recap first.',
                ! $category->isAnnounced(self::Invitations) => 'Send the Awarding Night invitations first.',
                default => null,
            },
        };
    }
}
