<?php

namespace App\Http\Requests\Admin;

use App\Models\AssessmentTemplate;
use App\Models\ScoringCriterion;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssessmentTemplateRequest extends FormRequest
{
    /**
     * The total weight every template's criteria must add up to.
     */
    public const int TOTAL_WEIGHT = 100;

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
        $template = $this->route('template');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(AssessmentTemplate::class)->ignore($template)],
            'description' => ['nullable', 'string', 'max:2000'],
            'criteria' => ['required', 'array', 'min:1', 'max:20'],
            'criteria.*.id' => $template instanceof AssessmentTemplate
                ? ['nullable', 'integer', 'distinct', Rule::exists(ScoringCriterion::class, 'id')->where('assessment_template_id', $template->id)]
                : ['prohibited'],
            'criteria.*.aspect' => ['required', 'string', 'max:255'],
            'criteria.*.criteria' => ['required', 'string', 'max:2000'],
            'criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.weight' => ['required', 'integer', 'min:1', 'max:'.self::TOTAL_WEIGHT],
        ];
    }

    /**
     * Reject criteria whose weights do not add up to 100%.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = array_sum(array_column($this->templateData()['criteria'], 'weight'));

                if ($total !== self::TOTAL_WEIGHT) {
                    $validator->errors()->add('criteria', "The weights must add up to 100% (currently {$total}%).");
                }
            },
        ];
    }

    /**
     * The validated template with its criteria in submitted order.
     *
     * @return array{name: string, description: string|null, criteria: list<array{id: int|null, aspect: string, criteria: string, description: string|null, weight: int}>}
     */
    public function templateData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'criteria' => array_map(fn (int|string $index): array => [
                'id' => $this->filled("criteria.{$index}.id") ? $this->integer("criteria.{$index}.id") : null,
                'aspect' => $this->string("criteria.{$index}.aspect")->toString(),
                'criteria' => $this->string("criteria.{$index}.criteria")->toString(),
                'description' => $this->filled("criteria.{$index}.description") ? $this->string("criteria.{$index}.description")->toString() : null,
                'weight' => $this->integer("criteria.{$index}.weight"),
            ], array_keys($this->array('criteria'))),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'criteria.*.aspect' => 'aspect',
            'criteria.*.criteria' => 'criteria',
            'criteria.*.description' => 'description',
            'criteria.*.weight' => 'weight',
        ];
    }
}
