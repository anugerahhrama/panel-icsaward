<?php

namespace App\Http\Requests\Admin;

use App\Enums\Award;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmAwardsRequest extends FormRequest
{
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
        return [
            'awards' => ['required', 'array', 'min:1', 'max:'.array_sum(array_map(fn (Award $award): int => $award->quota(), Award::cases()))],
            'awards.*.submission_id' => ['required', 'integer', 'distinct'],
            'awards.*.award' => ['required', Rule::enum(Award::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'awards.required' => 'Give at least one award.',
        ];
    }

    /**
     * The awards to give, keyed by submission id.
     *
     * @return array<int, Award>
     */
    public function awards(): array
    {
        $awards = [];

        foreach (array_keys((array) $this->input('awards', [])) as $index) {
            $awards[$this->integer("awards.{$index}.submission_id")] = Award::from($this->string("awards.{$index}.award")->value());
        }

        return $awards;
    }
}
