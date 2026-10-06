<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\TimelineStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistrationSettingsRequest extends FormRequest
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
            'is_registration_open' => ['nullable', 'boolean'],
            'registration_opens_at' => ['required', 'date_format:Y-m-d'],
            'registration_deadline' => ['required', 'date_format:Y-m-d', 'after_or_equal:registration_opens_at'],
            'paper_deadline' => ['required', 'date_format:Y-m-d', 'after_or_equal:registration_opens_at'],
            'max_registrations_per_user' => ['required', 'integer', 'min:1', 'max:20'],
            ...collect(TimelineStage::cases())
                ->flatMap(fn (TimelineStage $stage): array => [
                    $stage->dateKey() => ['nullable', 'string', 'max:255'],
                    $stage->titleKey() => ['nullable', 'string', 'max:100'],
                    $stage->descriptionKey() => ['nullable', 'string', 'max:500'],
                ])
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
            ...collect(TimelineStage::cases())
                ->flatMap(fn (TimelineStage $stage): array => [
                    $stage->dateKey() => "{$stage->defaultTitle()} date",
                    $stage->titleKey() => "{$stage->defaultTitle()} title",
                    $stage->descriptionKey() => "{$stage->defaultTitle()} description",
                ])
                ->all(),
        ];
    }
}
