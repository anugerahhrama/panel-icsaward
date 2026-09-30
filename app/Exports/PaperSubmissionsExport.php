<?php

namespace App\Exports;

use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Submission>
 */
class PaperSubmissionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array{search: string|null, category: int|null, status: string|null, sort: string}  $filters
     */
    public function __construct(public array $filters) {}

    /**
     * @return Builder<Submission>
     */
    public function query(): Builder
    {
        return Submission::query()
            ->whereNotNull('paper_uploaded_at')
            ->filteredForAdmin($this->filters);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Uploaded at (WIB)',
            'Name',
            'Email',
            'Company',
            'Category',
            'Initiative title',
            'Paper file',
            'Paper link',
            'Statement letter file',
            'Statement letter link',
            'Status',
        ];
    }

    /**
     * Download links point at the admin-only file route, so they only work for a signed-in committee member.
     *
     * @param  Submission  $row
     * @return list<string|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row->paper_uploaded_at?->timezone(Setting::EVENT_TIMEZONE)->format('Y-m-d H:i'),
            $row->user->name,
            $row->user->email,
            $row->user->company_name,
            $row->awardCategory->name,
            $row->initiative_title,
            $row->paper_original_name,
            $row->paper_path === null ? null : route('admin.participants.files.show', [$row, 'paper']),
            $row->statement_original_name,
            $row->statement_path === null ? null : route('admin.participants.files.show', [$row, 'statement']),
            Str::headline($row->status->value),
        ];
    }
}
