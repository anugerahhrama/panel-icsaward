<?php

use App\Mail\SubmissionConfirmation;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Setting::put('max_registrations_per_user', '3');
});

/**
 * @return array<string, mixed>
 */
function anotherInitiativePayload(AwardCategory $category, array $overrides = []): array
{
    return [
        'award_category_id' => $category->id,
        'initiative_title' => 'Mangrove Restoration Program',
        'initiative_description' => 'Planting 10,000 mangroves along the north coast.',
        'terms_accepted' => true,
        ...$overrides,
    ];
}

test('a participant below the limit sees the categories they already registered', function () {
    $submission = Submission::factory()->create();
    AwardCategory::factory()->create();

    $this->actingAs($submission->user)
        ->get(route('submissions.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('submissions/create')
            ->where('registeredCategoryIds', [$submission->award_category_id])
            ->has('categories', 2)
            ->has('terms'));
});

test('the form sends the participant back to the dashboard when they cannot register another initiative', function (string $key, string $value, string $message) {
    Setting::put($key, $value);
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->get(route('submissions.create'))
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => $message]);
})->with([
    'limit reached' => ['max_registrations_per_user', '1', 'You can register at most 1 initiative.'],
    'registration closed' => ['is_registration_open', '0', 'Registration is closed.'],
]);

test('staff cannot register an initiative', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get(route('submissions.create'))
        ->assertForbidden();
})->with(['admin', 'judge']);

test('a participant registers another initiative and receives its submission link', function () {
    Mail::fake();
    $submission = Submission::factory()->create();
    $category = AwardCategory::factory()->create();

    $response = $this->actingAs($submission->user)
        ->post(route('submissions.store'), anotherInitiativePayload($category));

    $registered = $submission->user->submissions()->where('award_category_id', $category->id)->sole();

    $response->assertRedirect(route('submissions.show', $registered));
    expect($registered->initiative_title)->toBe('Mangrove Restoration Program')
        ->and($registered->terms_accepted_at)->not->toBeNull()
        ->and($registered->confirmation_sent_at)->not->toBeNull();
    Mail::assertQueued(SubmissionConfirmation::class, fn (SubmissionConfirmation $mail) => $mail->submission->is($registered));
});

test('registering another initiative is rejected', function (Closure $arrange, string $field, string $message) {
    Mail::fake();
    $submission = Submission::factory()->create();
    $payload = $arrange($submission);

    $this->actingAs($submission->user)
        ->post(route('submissions.store'), $payload)
        ->assertSessionHasErrors([$field => $message]);

    expect(Submission::count())->toBe(1);
    Mail::assertNothingQueued();
})->with([
    'same category' => [
        fn (Submission $submission) => anotherInitiativePayload($submission->awardCategory),
        'award_category_id',
        'You have already registered an initiative in this category.',
    ],
    'limit reached' => [
        function (Submission $submission) {
            Setting::put('max_registrations_per_user', '1');

            return anotherInitiativePayload(AwardCategory::factory()->create());
        },
        'award_category_id',
        'You can register at most 1 initiative.',
    ],
    'registration closed' => [
        function (Submission $submission) {
            Setting::put('is_registration_open', '0');

            return anotherInitiativePayload(AwardCategory::factory()->create());
        },
        'registration',
        'Registration is closed.',
    ],
    'terms not accepted' => [
        fn (Submission $submission) => anotherInitiativePayload(AwardCategory::factory()->create(), ['terms_accepted' => false]),
        'terms_accepted',
        'You must agree to the terms and conditions.',
    ],
]);

test('the initiative step rejects a category the participant already registered', function () {
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->postJson(route('submissions.validate'), anotherInitiativePayload($submission->awardCategory, ['terms_accepted' => null]))
        ->assertOnlyJsonValidationErrors(['award_category_id' => 'You have already registered an initiative in this category.']);
});

test('the initiative step accepts a new category without the terms', function () {
    $submission = Submission::factory()->create();

    $this->actingAs($submission->user)
        ->postJson(route('submissions.validate'), anotherInitiativePayload(AwardCategory::factory()->create(), ['terms_accepted' => null]))
        ->assertNoContent();
});
