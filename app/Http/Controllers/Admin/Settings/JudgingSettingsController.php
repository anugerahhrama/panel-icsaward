<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Judging\CalculateStageTwoScores;
use App\Actions\Judging\StageScoreCalculator;
use App\Enums\JudgingStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateJudgingSettingsRequest;
use App\Models\AwardCategory;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JudgingSettingsController extends Controller
{
    /**
     * Show the judging settings.
     */
    public function edit(): Response
    {
        [$stageOneWeight, $stageTwoWeight] = CalculateStageTwoScores::weights();

        return Inertia::render('admin/settings/judging', [
            'settings' => [
                'judging_stage' => JudgingStage::current()->value,
                'normalization_enabled' => Setting::get(StageScoreCalculator::NORMALIZATION_ENABLED_KEY, '1') === '1',
                'normalization_min_sample' => (int) Setting::get(StageScoreCalculator::NORMALIZATION_MIN_SAMPLE_KEY, (string) StageScoreCalculator::DEFAULT_MIN_SAMPLE),
                'stage_1_weight' => $stageOneWeight,
                'stage_2_weight' => $stageTwoWeight,
            ],
            'weightsLocked' => AwardCategory::query()->awardsConfirmed()->exists(),
            'stages' => collect(JudgingStage::selectable())
                ->map(fn (JudgingStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()])
                ->values(),
        ]);
    }

    /**
     * Open or close a judging stage and save the normalization settings shared by both stages and the final score weights.
     *
     * The row is locked first, so a stage change waits for scores being saved under the previous stage.
     */
    public function update(UpdateJudgingSettingsRequest $request): RedirectResponse
    {
        $stage = $request->stage();

        DB::transaction(function () use ($request, $stage): void {
            JudgingStage::current(lock: true);

            Setting::put(JudgingStage::SETTING_KEY, $stage->value);

            foreach ([...$request->normalizationSettings(), ...$request->weightSettings()] as $key => $value) {
                Setting::put($key, $value);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Judging settings saved. Active stage: {$stage->label()}."]);

        return to_route('admin.settings.judging.edit');
    }
}
