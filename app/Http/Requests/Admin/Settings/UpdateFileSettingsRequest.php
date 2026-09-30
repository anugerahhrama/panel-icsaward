<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateFileSettingsRequest extends FormRequest
{
    /**
     * File types an admin may allow for the participant's Submission Paper.
     *
     * @var list<string>
     */
    public const array PAPER_EXTENSION_OPTIONS = ['pdf', 'pptx', 'ppt', 'doc', 'docx'];

    /**
     * File types accepted for the downloadable templates.
     *
     * @var list<string>
     */
    public const array TEMPLATE_EXTENSIONS = ['pdf', 'doc', 'docx'];

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
        $template = ['nullable', File::types(self::TEMPLATE_EXTENSIONS)->max(10 * 1024)];

        return [
            'submission_template' => $template,
            'remove_submission_template' => ['boolean'],
            'statement_letter_template' => $template,
            'remove_statement_letter_template' => ['boolean'],
            'paper_allowed_extensions' => ['required', 'array', 'min:1'],
            'paper_allowed_extensions.*' => ['string', Rule::in(self::PAPER_EXTENSION_OPTIONS)],
            'paper_max_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'require_statement_letter' => ['required', 'boolean'],
            'terms_organization' => ['required', 'string', 'max:20000'],
            'terms_individual' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'submission_template' => 'submission paper template',
            'statement_letter_template' => 'statement letter template',
            'paper_allowed_extensions' => 'allowed file types',
            'paper_allowed_extensions.*' => 'allowed file type',
            'paper_max_size_mb' => 'maximum file size',
            'terms_organization' => 'organization terms',
            'terms_individual' => 'individual terms',
        ];
    }
}
