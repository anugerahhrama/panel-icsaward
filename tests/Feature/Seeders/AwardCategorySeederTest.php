<?php

use App\Enums\ApplicantType;
use App\Models\AwardCategory;
use Database\Seeders\AwardCategorySeeder;

test('the award category seeder can be run repeatedly without duplicating categories', function () {
    $this->seed(AwardCategorySeeder::class);
    $this->seed(AwardCategorySeeder::class);

    expect(AwardCategory::count())->toBe(20)
        ->and(AwardCategory::whereNull('description')->count())->toBe(0)
        ->and(AwardCategory::where('applicant_type', ApplicantType::Individual)->orderBy('sort_order')->pluck('sort_order')->all())
        ->toBe([19, 20]);
});

test('the award category seeder renames a previously seeded category in place', function () {
    $category = AwardCategory::factory()->individual()->create([
        'name' => 'Best Sustainability Leader (Middle Management)',
        'description' => null,
    ]);

    $this->seed(AwardCategorySeeder::class);

    expect(AwardCategory::count())->toBe(20)
        ->and($category->fresh()->name)->toBe('Best Sustainability Leader (Middle Level Management)')
        ->and($category->fresh()->description)->not->toBeNull();
});
