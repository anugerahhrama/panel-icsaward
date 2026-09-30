<?php

namespace App\Http\Requests\Admin;

use App\Enums\ApplicantType;
use App\Http\Requests\Admin\Settings\UpdateFileSettingsRequest;
use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class CategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', Rule::unique(AwardCategory::class)->ignore($this->route('category'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'applicant_type' => ['required', Rule::enum(ApplicantType::class)],
            'assessment_template_id' => ['nullable', 'integer', Rule::exists(AssessmentTemplate::class, 'id')],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'paper_template' => ['nullable', File::types(UpdateFileSettingsRequest::TEMPLATE_EXTENSIONS)->max(10 * 1024)],
            'remove_paper_template' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'applicant_type' => 'applicant type',
            'assessment_template_id' => 'assessment template',
            'sort_order' => 'sort order',
            'paper_template' => 'paper template',
        ];
    }

    /**
     * The validated category fields.
     *
     * @return array{name: string, description: string|null, applicant_type: string, assessment_template_id: int|null, sort_order: int}
     */
    public function categoryData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'applicant_type' => $this->string('applicant_type')->toString(),
            'assessment_template_id' => $this->filled('assessment_template_id') ? $this->integer('assessment_template_id') : null,
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    /**
     * The newly uploaded paper template, if any.
     */
    public function paperTemplate(): ?UploadedFile
    {
        $template = $this->file('paper_template');

        return $template instanceof UploadedFile ? $template : null;
    }
}
