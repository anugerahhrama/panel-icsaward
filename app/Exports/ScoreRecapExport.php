<?php

namespace App\Exports;

use App\Enums\ScoreRecapStage;
use App\Enums\SubmissionStatus;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Submission>
 */
class ScoreRecapExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array{stage: ScoreRecapStage, search: string|null, category: int|null, sort: string}  $filters
     */
    public function __construct(public array $filters) {}

    /**
     * @return Builder<Submission>
     */
    public function query(): Builder
    {
        return Submission::query()->scoreRecapForAdmin($this->filters);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        if ($this->filters['stage'] === ScoreRecapStage::Final) {
            return [
                'Category',
                'Final rank',
                'Name',
                'Email',
                'Company',
                'Initiative title',
                'Stage 1 score',
                'Stage 2 score',
                'Final score',
                'Award',
                'Calculated at (WIB)',
            ];
        }

        if ($this->filters['stage'] === ScoreRecapStage::Pitching) {
            return [
                'Category',
                'Stage 2 rank',
                'Name',
                'Email',
                'Company',
                'Initiative title',
                'Judges submitted',
                'Judges assigned',
                'Stage 1 score',
                'Stage 2 raw score',
                'Stage 2 normalized score',
                'Calculated at (WIB)',
            ];
        }

        return [
            'Category',
            'Rank',
            'Name',
            'Email',
            'Company',
            'Initiative title',
            'Judges submitted',
            'Judges assigned',
            'Raw score',
            'Normalized score',
            'Finalist',
            'Calculated at (WIB)',
        ];
    }

    /**
     * @param  Submission  $row
     * @return list<string|int|float|null>
     */
    public function map(mixed $row): array
    {
        if ($this->filters['stage'] === ScoreRecapStage::Final) {
            return [
                $row->awardCategory->name,
                $row->final_rank,
                $row->user->name,
                $row->user->email,
                $row->user->company_name,
                $row->initiative_title,
                $row->stage1_score,
                $row->stage2_score,
                $row->final_score,
                $row->award?->label(),
                $row->final_calculated_at?->timezone(Setting::EVENT_TIMEZONE)->format('Y-m-d H:i'),
            ];
        }

        if ($this->filters['stage'] === ScoreRecapStage::Pitching) {
            return [
                $row->awardCategory->name,
                $row->stage2_rank,
                $row->user->name,
                $row->user->email,
                $row->user->company_name,
                $row->initiative_title,
                $row->stage2_judges_submitted,
                $row->stage2_judges_assigned,
                $row->stage1_score,
                $row->stage2_raw_score,
                $row->stage2_score,
                $row->stage2_calculated_at?->timezone(Setting::EVENT_TIMEZONE)->format('Y-m-d H:i'),
            ];
        }

        return [
            $row->awardCategory->name,
            $row->stage1_rank,
            $row->user->name,
            $row->user->email,
            $row->user->company_name,
            $row->initiative_title,
            $row->stage1_judges_submitted,
            $row->stage1_judges_assigned,
            $row->stage1_raw_score,
            $row->stage1_score,
            $row->status === SubmissionStatus::Finalist ? 'Yes' : null,
            $row->stage1_calculated_at?->timezone(Setting::EVENT_TIMEZONE)->format('Y-m-d H:i'),
        ];
    }
}
