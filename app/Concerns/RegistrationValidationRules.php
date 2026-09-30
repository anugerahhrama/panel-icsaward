<?php

namespace App\Concerns;

use App\Enums\RegistrationStatus;
use App\Models\AwardCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

trait RegistrationValidationRules
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Reject the sign up attempt while registration is closed or not open yet.
     *
     * @throws ValidationException
     */
    protected function ensureRegistrationIsOpen(): void
    {
        $status = RegistrationStatus::current();

        if (! $status->isOpen()) {
            throw ValidationException::withMessages(['registration' => $status->rejectionMessage()]);
        }
    }

    /**
     * Get the validation rules for the "Account" step of sign up.
     *
     * @return array<string, array<int, Password|ValidationRule|array<mixed>|string>>
     */
    protected function accountRules(): array
    {
        return [
            ...$this->profileRules(),
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{6,}$/'],
            'position' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * Get the validation rules for the "Initiative" step of sign up.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function initiativeRules(): array
    {
        return [
            'award_category_id' => ['required', 'integer', Rule::exists(AwardCategory::class, 'id')],
            'initiative_title' => ['required', 'string', 'max:255'],
            'initiative_description' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the validation rules for the "Terms" step of sign up.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function termsRules(): array
    {
        return [
            'terms_accepted' => ['accepted'],
        ];
    }

    /**
     * Get the custom validation messages shared by every sign up step.
     *
     * @return array<string, string>
     */
    protected function registrationMessages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid phone number.',
            'award_category_id.required' => 'Please choose an award category.',
            'award_category_id.exists' => 'The selected award category is not available.',
            'terms_accepted.accepted' => 'You must agree to the terms and conditions.',
        ];
    }
}
