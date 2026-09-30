<?php

namespace App\Http\Requests\Judge;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScoreRequest extends FormRequest
{
    /**
     * The policy check lives in the route's controller (`score` ability).
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
        return [
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'scores' => ['required', 'array', 'max:50'],
            'scores.*.criterion_id' => ['required', 'integer', 'distinct'],
            'scores.*.raw_score' => ['nullable', 'required_if:action,submit', 'integer', 'min:0', 'max:100'],
            'scores.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scores.*.raw_score' => 'score',
            'scores.*.notes' => 'notes',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scores.*.raw_score.required_if' => 'Enter a score before submitting.',
        ];
    }

    public function isSubmit(): bool
    {
        return $this->input('action') === 'submit';
    }

    /**
     * The validated score rows in a typed shape.
     *
     * @return list<array{criterion_id: int, raw_score: int|null, notes: string|null}>
     */
    public function scoresData(): array
    {
        $rows = [];

        foreach (array_keys((array) $this->validated('scores')) as $index) {
            $notes = trim($this->string("scores.{$index}.notes")->toString());

            $rows[] = [
                'criterion_id' => $this->integer("scores.{$index}.criterion_id"),
                'raw_score' => $this->filled("scores.{$index}.raw_score") ? $this->integer("scores.{$index}.raw_score") : null,
                'notes' => $notes === '' ? null : $notes,
            ];
        }

        return $rows;
    }
}
