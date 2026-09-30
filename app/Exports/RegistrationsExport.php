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
class RegistrationsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
        return Submission::query()->filteredForAdmin($this->filters);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Registered at (WIB)',
            'Name',
            'Phone',
            'Email',
            'Position',
            'Company',
            'Category',
            'Initiative title',
            'Initiative description',
            'Terms accepted at (WIB)',
            'Status',
        ];
    }

    /**
     * @param  Submission  $row
     * @return list<string|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row->created_at?->timezone(Setting::EVENT_TIMEZONE)->format('Y-m-d H:i'),
            $row->user->name,
            $row->user->phone,
            $row->user->email,
            $row->user->position,
            $row->user->company_name,
            $row->awardCategory->name,
            $row->initiative_title,
            $row->initiative_description,
            $row->terms_accepted_at?->timezone(Setting::EVENT_TIMEZONE)->format('Y-m-d H:i'),
            Str::headline($row->status->value),
        ];
    }
}
