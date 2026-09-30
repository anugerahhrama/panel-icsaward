<?php

namespace App\Enums;

/**
 * The tabs of the Score Recap: the results of each judging stage, and the final results that combine them.
 */
enum ScoreRecapStage: string
{
    case DeskEvaluation = 'desk_evaluation';
    case Pitching = 'pitching';
    case Final = 'final';

    /**
     * The prefix of the `submissions` columns holding this tab's stored results.
     *
     * @return 'stage1'|'stage2'|'final'
     */
    public function prefix(): string
    {
        return match ($this) {
            self::DeskEvaluation => 'stage1',
            self::Pitching => 'stage2',
            self::Final => 'final',
        };
    }

    /**
     * Whether the tab lists only the finalists of categories with confirmed finalists.
     */
    public function listsFinalists(): bool
    {
        return $this !== self::DeskEvaluation;
    }
}
