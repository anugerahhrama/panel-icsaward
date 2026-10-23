<?php

namespace App\Concerns;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Models\AwardCategory;
use App\Models\User;
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
     * Get the verified Google profile of a participant signing up with Google, if any.
     *
     * @return array{name: string, email: string}|null
     */
    protected function googleSignup(): ?array
    {
        /** @var array{name: string, email: string}|null $profile */
        $profile = session()->get(GoogleLoginController::SIGNUP_SESSION_KEY);

        return $profile;
    }

    /**
     * Get the validation rules for the "Account" step of sign up.
     *
     * A Google sign up has no password and must keep the email Google verified.
     *
     * @return array<string, array<int, Password|ValidationRule|array<mixed>|string>>
     */
    protected function accountRules(): array
    {
        $googleSignup = $this->googleSignup();

        if ($googleSignup !== null) {
            return [
                'name' => $this->nameRules(),
                'email' => ['required', 'string', 'email', Rule::in([$googleSignup['email']]), Rule::unique(User::class)],
                ...$this->participantProfileRules(),
            ];
        }

        return [
            ...$this->profileRules(),
            ...$this->participantProfileRules(),
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
            ...$this->participantProfileMessages(),
            'email.in' => 'Please use the email from your Google account.',
            'award_category_id.required' => 'Please choose an award category.',
            'award_category_id.exists' => 'The selected award category is not available.',
            'terms_accepted.accepted' => 'You must agree to the terms and conditions.',
        ];
    }
}
