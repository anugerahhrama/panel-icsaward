<?php

namespace App\Enums;

use App\Models\AwardCategory;
use App\Models\Setting;

enum JudgingStage: string
{
    case Closed = 'closed';
    case DeskEvaluation = 'desk_evaluation';
    case Pitching = 'pitching';

    /**
     * The setting key holding the active stage.
     */
    public const string SETTING_KEY = 'judging_stage';

    /**
     * Resolve the active judging stage from the settings; a missing or unknown value means judging is closed.
     *
     * Pass `$lock` inside a transaction that writes scores, so a concurrent stage change waits for it (and vice versa).
     */
    public static function current(bool $lock = false): self
    {
        $value = Setting::query()
            ->where('key', self::SETTING_KEY)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->value('value');

        return self::tryFrom((string) $value) ?? self::Closed;
    }

    /**
     * The stages an admin may open.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Closed, self::DeskEvaluation, self::Pitching];
    }

    /**
     * The stage whose submissions and scores judges see: the active stage, or while judging is closed, pitching once
     * any category has confirmed finalists and desk evaluation before that.
     */
    public static function forJudges(): self
    {
        $current = self::current();

        if ($current !== self::Closed) {
            return $current;
        }

        return AwardCategory::query()->finalistsConfirmed()->exists() ? self::Pitching : self::DeskEvaluation;
    }

    /**
     * Human readable label, matching `JUDGING_STAGE_LABELS` on the frontend.
     */
    public function label(): string
    {
        return match ($this) {
            self::Closed => 'Closed',
            self::DeskEvaluation => 'Stage 1 · Desk Evaluation',
            self::Pitching => 'Stage 2 · Pitching',
        };
    }
}
