<?php

namespace App\Enums;

enum Award: string
{
    case Gold = 'gold';
    case Silver = 'silver';
    case Bronze = 'bronze';

    /**
     * How many of this award a category may give at most.
     */
    public function quota(): int
    {
        return match ($this) {
            self::Gold => 1,
            self::Silver, self::Bronze => 2,
        };
    }

    /**
     * The award suggested for a final rank: 1 Gold, 2–3 Silver, 4–5 Bronze, none below. Only a suggestion; the
     * committee confirms the awards.
     */
    public static function suggestedFor(?int $rank): ?self
    {
        return match (true) {
            $rank === null => null,
            $rank === 1 => self::Gold,
            $rank <= 3 => self::Silver,
            $rank <= 5 => self::Bronze,
            default => null,
        };
    }

    /**
     * Human readable label, matching `AWARD_LABELS` on the frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gold => 'Gold',
            self::Silver => 'Silver',
            self::Bronze => 'Bronze',
        };
    }
}
