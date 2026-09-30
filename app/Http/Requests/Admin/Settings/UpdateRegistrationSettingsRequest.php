<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationSettingsRequest extends FormRequest
{
    /**
     * Free-text stage dates shown in the participant "What's next" panel.
     *
     * @var list<string>
     */
    public const array TIMELINE_KEYS = [
        'timeline_administrative_selection',
        'timeline_desk_evaluation',
        'timeline_finalists_announcement',
        'timeline_pitching',
        'timeline_awarding_night',
    ];

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
            'is_registration_open' => ['nullable', 'boolean'],
            'registration_opens_at' => ['required', 'date_format:Y-m-d'],
            'registration_deadline' => ['required', 'date_format:Y-m-d', 'after_or_equal:registration_opens_at'],
            'paper_deadline' => ['required', 'date_format:Y-m-d', 'after_or_equal:registration_opens_at'],
            'max_registrations_per_user' => ['required', 'integer', 'min:1', 'max:20'],
            ...collect(self::TIMELINE_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => ['nullable', 'string', 'max:255']])
                ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'registration_opens_at' => 'registration opening date',
            'registration_deadline' => 'registration deadline',
            'paper_deadline' => 'paper deadline',
            'max_registrations_per_user' => 'registrations per account',
        ];
    }
}
