<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Registered = 'registered';
    case UnderReview = 'under_review';
    case NeedsRevision = 'needs_revision';
    case Qualified = 'qualified';
    case Disqualified = 'disqualified';
    case Finalist = 'finalist';

    /**
     * The outcomes the committee may choose during administrative verification.
     *
     * @return list<self>
     */
    public static function verificationDecisions(): array
    {
        return [self::Qualified, self::NeedsRevision, self::Disqualified];
    }

    /**
     * The statuses that carry a Stage 1 score: qualified submissions, and those the committee confirmed as finalists.
     *
     * @return list<self>
     */
    public static function rankable(): array
    {
        return [self::Qualified, self::Finalist];
    }

    /**
     * Human readable label, matching `STATUS_LABELS` on the frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Awaiting paper',
            self::UnderReview => 'Under review',
            self::NeedsRevision => 'Needs revision',
            self::Qualified => 'Qualified',
            self::Disqualified => 'Disqualified',
            self::Finalist => 'Finalist',
        };
    }
}
