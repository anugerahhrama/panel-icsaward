<?php

use App\Actions\Submissions\RegisterSubmission;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * @return array{initiative_title: string, initiative_description: string}
 */
function initiativeData(): array
{
    return [
        'initiative_title' => 'Solar Rooftops for Schools',
        'initiative_description' => 'Installing rooftop solar on 50 rural schools.',
    ];
}

test('a user cannot register twice in the same category', function () {
    Setting::put('max_registrations_per_user', '5');
    $submission = Submission::factory()->create();

    expect(fn () => app(RegisterSubmission::class)->handle($submission->user, $submission->awardCategory, initiativeData()))
        ->toThrow(ValidationException::class, 'You have already registered an initiative in this category.');

    expect(Submission::count())->toBe(1);
});

test('a user cannot exceed the default limit of one registration', function () {
    $submission = Submission::factory()->create();

    expect(fn () => app(RegisterSubmission::class)->handle($submission->user, AwardCategory::factory()->create(), initiativeData()))
        ->toThrow(ValidationException::class, 'You can register at most 1 initiative.');

    expect(Submission::count())->toBe(1);
});

test('a user can register in another category when the limit allows it', function () {
    Setting::put('max_registrations_per_user', '2');
    $submission = Submission::factory()->create();
    $category = AwardCategory::factory()->create();

    $second = app(RegisterSubmission::class)->handle($submission->user, $category, initiativeData());

    expect($second->user_id)->toBe($submission->user_id)
        ->and($second->award_category_id)->toBe($category->id)
        ->and($second->terms_accepted_at)->not->toBeNull()
        ->and(User::find($submission->user_id)->submissions()->count())->toBe(2);
});
