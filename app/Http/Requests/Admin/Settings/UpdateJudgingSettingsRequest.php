<?php

namespace App\Http\Requests\Admin\Settings;

use App\Actions\Judging\CalculateStageTwoScores;
use App\Enums\JudgingStage;
use App\Models\AwardCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateJudgingSettingsRequest extends FormRequest
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
            'judging_stage' => ['required', Rule::enum(JudgingStage::class)->only(JudgingStage::selectable())],
            'normalization_enabled' => ['nullable', 'boolean'],
            'normalization_min_sample' => ['required', 'integer', 'min:2', 'max:50'],
            'stage_1_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'stage_2_weight' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * The weights must add up to 100, and are frozen once any category has confirmed awards.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['stage_1_weight', 'stage_2_weight'])) {
                    return;
                }

                [$stageOneWeight, $stageTwoWeight] = $this->weights();

                if ($stageOneWeight + $stageTwoWeight !== 100) {
                    $validator->errors()->add('stage_1_weight', 'The Stage 1 and Stage 2 weights must add up to 100.');

                    return;
                }

                if ([$stageOneWeight, $stageTwoWeight] !== CalculateStageTwoScores::weights() && AwardCategory::query()->awardsConfirmed()->exists()) {
                    $validator->errors()->add('stage_1_weight', 'Awards are already confirmed, so the weights can no longer change.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'judging_stage' => 'judging stage',
            'normalization_enabled' => 'normalization',
            'normalization_min_sample' => 'minimum sample',
            'stage_1_weight' => 'Stage 1 weight',
            'stage_2_weight' => 'Stage 2 weight',
        ];
    }

    /**
     * The normalization settings as stored strings.
     *
     * @return array{normalization_enabled: string, normalization_min_sample: string}
     */
    public function normalizationSettings(): array
    {
        return [
            'normalization_enabled' => $this->boolean('normalization_enabled') ? '1' : '0',
            'normalization_min_sample' => (string) $this->integer('normalization_min_sample'),
        ];
    }

    /**
     * The Stage 1 and Stage 2 weights (percent).
     *
     * @return array{int, int}
     */
    public function weights(): array
    {
        return [$this->integer('stage_1_weight'), $this->integer('stage_2_weight')];
    }

    /**
     * The weights as stored strings.
     *
     * @return array<string, string>
     */
    public function weightSettings(): array
    {
        [$stageOneWeight, $stageTwoWeight] = $this->weights();

        return [
            CalculateStageTwoScores::STAGE_1_WEIGHT_KEY => (string) $stageOneWeight,
            CalculateStageTwoScores::STAGE_2_WEIGHT_KEY => (string) $stageTwoWeight,
        ];
    }

    public function stage(): JudgingStage
    {
        return $this->enum('judging_stage', JudgingStage::class) ?? JudgingStage::Closed;
    }
}
