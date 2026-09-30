<?php

namespace App\Http\Requests\Admin;

use App\Actions\Judging\ConfirmFinalists;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmFinalistsRequest extends FormRequest
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
            'submission_ids' => ['required', 'array', 'min:1', 'max:'.ConfirmFinalists::MAX_FINALISTS],
            'submission_ids.*' => ['integer', 'distinct'],
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
            'submission_ids.required' => 'Select at least one finalist.',
            'submission_ids.max' => 'Select at most '.ConfirmFinalists::MAX_FINALISTS.' finalists.',
        ];
    }

    /**
     * The selected submission ids.
     *
     * @return list<int>
     */
    public function submissionIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('submission_ids', [])));
    }
}
