<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubmissionStatus;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifySubmissionRequest extends FormRequest
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
            'decision' => ['required', Rule::in(array_column(SubmissionStatus::verificationDecisions(), 'value'))],
            'revision_note' => ['nullable', 'required_if:decision,needs_revision', 'string', 'max:5000'],
            'revision_deadline' => [
                'nullable',
                'required_if:decision,needs_revision',
                'date_format:Y-m-d',
                'after_or_equal:'.now(Setting::EVENT_TIMEZONE)->toDateString(),
            ],
            'disqualified_reason' => ['nullable', 'required_if:decision,disqualified', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'revision_note' => 'revision note',
            'revision_deadline' => 'revision deadline',
            'disqualified_reason' => 'reason',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'revision_deadline.after_or_equal' => 'The revision deadline cannot be in the past.',
        ];
    }

    public function decision(): SubmissionStatus
    {
        return SubmissionStatus::from($this->string('decision')->value());
    }

    /**
     * The decision details, with the revision deadline moved to the end of that day in WIB (like `Setting::endOfDay`).
     *
     * The deadline is converted to the app timezone because it is written with a query builder `update()`,
     * which stores a Carbon value as-is instead of converting it like a model attribute would.
     *
     * @return array{revision_note: string|null, revision_deadline: CarbonImmutable|null, disqualified_reason: string|null}
     */
    public function details(): array
    {
        $deadline = $this->validated('revision_deadline');

        return [
            'revision_note' => $this->validated('revision_note'),
            'revision_deadline' => $deadline === null
                ? null
                : CarbonImmutable::createFromFormat('!Y-m-d', $deadline, Setting::EVENT_TIMEZONE)?->endOfDay()->setTimezone(config('app.timezone')),
            'disqualified_reason' => $this->validated('disqualified_reason'),
        ];
    }
}
