<?php

namespace App\Http\Requests;

use App\Concerns\RegistrationValidationRules;
use App\Models\Submission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterInitiativeRequest extends FormRequest
{
    use RegistrationValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * Only participants reach this request (`role:participant` on the routes).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reject the request while registration is closed, before any field is validated.
     */
    protected function prepareForValidation(): void
    {
        $this->ensureRegistrationIsOpen();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The "Initiative" step validation (`submissions.validate`) checks the initiative fields only;
     * the final submission also requires the terms.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->initiativeRules();

        $rules['award_category_id'][] = Rule::unique(Submission::class, 'award_category_id')
            ->where('user_id', $this->user()?->id);

        if ($this->routeIs('submissions.validate')) {
            return $rules;
        }

        return [...$rules, ...$this->termsRules()];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->registrationMessages(),
            'award_category_id.unique' => 'You have already registered an initiative in this category.',
        ];
    }

    /**
     * Get the validated initiative details.
     *
     * @return array{initiative_title: string, initiative_description: string}
     */
    public function initiative(): array
    {
        return [
            'initiative_title' => $this->string('initiative_title')->toString(),
            'initiative_description' => $this->string('initiative_description')->toString(),
        ];
    }
}
