<?php

use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use App\Models\ScoringCriterion;
use Database\Seeders\AssessmentTemplateSeeder;
use Database\Seeders\AwardCategorySeeder;

test('the assessment template seeder assigns a complete rubric to every category and can be run repeatedly', function () {
    $this->seed(AwardCategorySeeder::class);
    $this->seed(AssessmentTemplateSeeder::class);
    $this->seed(AssessmentTemplateSeeder::class);

    $templates = AssessmentTemplate::withSum('criteria', 'weight')->get();

    expect($templates)->toHaveCount(6)
        ->and($templates->pluck('criteria_sum_weight')->unique()->all())->toBe([100])
        ->and(ScoringCriterion::count())->toBe(29)
        ->and(AwardCategory::whereNull('assessment_template_id')->count())->toBe(0);
});
