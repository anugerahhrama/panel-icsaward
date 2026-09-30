<?php

namespace App\Http\Requests\Admin;

use App\Enums\ScoreRecapStage;
use App\Models\AwardCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScoreRecapFilterRequest extends FormRequest
{
    /**
     * Columns the Score Recap table may be sorted by; prefix with `-` for descending.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['rank', 'score', 'raw_score', 'name', 'category', 'initiative_title'];

    public const string DEFAULT_SORT = 'rank';

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
            'stage' => ['nullable', Rule::enum(ScoreRecapStage::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', Rule::exists(AwardCategory::class, 'id')],
            'sort' => ['nullable', Rule::in($sortable)],
            'per_page' => ['nullable', 'integer', Rule::in(ParticipantFilterRequest::PER_PAGE_OPTIONS)],
        ];
    }

    /**
     * The validated filters with defaults applied, shared by the table and its Excel export.
     *
     * @return array{stage: ScoreRecapStage, search: string|null, category: int|null, sort: string, per_page: int}
     */
    public function filters(): array
    {
        return [
            'stage' => $this->enum('stage', ScoreRecapStage::class) ?? ScoreRecapStage::DeskEvaluation,
            'search' => $this->validated('search'),
            'category' => $this->filled('category') ? (int) $this->validated('category') : null,
            'sort' => $this->validated('sort') ?? self::DEFAULT_SORT,
            'per_page' => (int) ($this->validated('per_page') ?? 50),
        ];
    }
}
