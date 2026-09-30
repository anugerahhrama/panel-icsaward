<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailSettingsRequest extends FormRequest
{
    /**
     * Prefixes of the admin-editable email templates, each stored as `{prefix}_email_subject` and `{prefix}_email_body`.
     *
     * @var list<string>
     */
    public const array TEMPLATES = ['confirmation', 'qualified', 'needs_revision', 'disqualified'];

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
        $rules = [];

        foreach (self::TEMPLATES as $template) {
            $rules["{$template}_email_subject"] = ['required', 'string', 'max:255'];
            $rules["{$template}_email_body"] = ['required', 'string', 'max:20000'];
        }

        return [
            ...$rules,
            'contact_email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (self::TEMPLATES as $template) {
            $attributes["{$template}_email_subject"] = 'subject';
            $attributes["{$template}_email_body"] = 'body';
        }

        return [
            ...$attributes,
            'contact_email' => 'contact email',
        ];
    }
}
