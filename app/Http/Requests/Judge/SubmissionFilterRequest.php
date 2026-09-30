<?php

namespace App\Http\Requests\Judge;

use App\Models\AwardCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmissionFilterRequest extends FormRequest
{
    /**
     * Columns "My Submissions" may be sorted by; prefix with `-` for descending.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['paper_uploaded_at', 'initiative_title', 'company', 'category', 'scored_at'];

    /**
     * @var list<string>
     */
    public const array SCORING_STATUSES = ['not_started', 'draft', 'submitted'];

    /**
     * @var list<int>
     */
    public const array PER_PAGE_OPTIONS = [10, 20, 50, 100];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sortable = array_merge(self::SORTABLE, array_map(fn (string $column): string => "-{$column}", self::SORTABLE));

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', Rule::exists(AwardCategory::class, 'id')],
            'scoring_status' => ['nullable', Rule::in(self::SCORING_STATUSES)],
            'sort' => ['nullable', Rule::in($sortable)],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE_OPTIONS)],
        ];
    }

    /**
     * The validated filters with defaults applied.
     *
     * @return array{search: string|null, category: int|null, scoring_status: string|null, sort: string, per_page: int}
     */
    public function filters(string $defaultSort): array
    {
        return [
            'search' => $this->validated('search'),
            'category' => $this->filled('category') ? (int) $this->validated('category') : null,
            'scoring_status' => $this->validated('scoring_status'),
            'sort' => $this->validated('sort') ?? $defaultSort,
            'per_page' => (int) ($this->validated('per_page') ?? 20),
        ];
    }
}
