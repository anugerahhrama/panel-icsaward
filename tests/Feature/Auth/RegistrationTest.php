<?php

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

/**
 * @return array<string, mixed>
 */
function registrationPayload(AwardCategory $category, array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'phone' => '+62 812-3456-7890',
        'email' => 'test@example.com',
        'position' => 'Sustainability Manager',
        'company_name' => 'PT Example Indonesia',
        'password' => 'password',
        'password_confirmation' => 'password',
        'award_category_id' => $category->id,
        'initiative_title' => 'Solar Rooftops for Schools',
        'initiative_description' => 'Installing rooftop solar on 50 rural schools.',
        'terms_accepted' => true,
        ...$overrides,
    ];
}

test('registration screen lists categories in order with the terms for each applicant type', function () {
    $second = AwardCategory::factory()->individual()->create(['sort_order' => 2, 'paper_template_path' => 'categories/individual.pdf']);
    $first = AwardCategory::factory()->create(['sort_order' => 1]);
    Setting::put('terms_organization', 'Organization terms');
    Setting::put('terms_individual', 'Individual terms');
    Setting::put('submission_template_path', 'settings/default.pdf');

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/register')
            ->where('categories.0.id', $first->id)
            ->where('categories.1.id', $second->id)
            ->where('categories.1.applicant_type', 'individual')
            ->where('terms.organization', 'Organization terms')
            ->where('terms.individual', 'Individual terms')
            ->where('categories.0.paper_template_url', Storage::disk('public')->url('settings/default.pdf'))
            ->where('categories.1.paper_template_url', Storage::disk('public')->url('categories/individual.pdf'))
            ->missing('categories.1.paper_template_path'));
});

test('new participants can register with their first initiative', function () {
    $category = AwardCategory::factory()->create();

    $response = $this->post(route('register.store'), registrationPayload($category));

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = auth()->user();
    expect($user->role)->toBe(UserRole::Participant)
        ->and($user->phone)->toBe('+62 812-3456-7890')
        ->and($user->position)->toBe('Sustainability Manager')
        ->and($user->company_name)->toBe('PT Example Indonesia');

    $submission = $user->submissions()->sole();
    expect($submission->award_category_id)->toBe($category->id)
        ->and($submission->initiative_title)->toBe('Solar Rooftops for Schools')
        ->and($submission->status)->toBe(SubmissionStatus::Registered)
        ->and($submission->terms_accepted_at)->not->toBeNull()
        ->and($submission->uuid)->toBeUuid();
});

test('registration is rejected when the terms are not accepted', function () {
    $category = AwardCategory::factory()->create();

    $this->post(route('register.store'), registrationPayload($category, ['terms_accepted' => false]))
        ->assertSessionHasErrors(['terms_accepted' => 'You must agree to the terms and conditions.']);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('registration is rejected for an unknown award category', function () {
    $category = AwardCategory::factory()->create();

    $this->post(route('register.store'), registrationPayload($category, ['award_category_id' => $category->id + 1]))
        ->assertSessionHasErrors(['award_category_id' => 'The selected award category is not available.']);

    $this->assertGuest();
});

test('the registration screen shows the closed page when an admin closes registration', function () {
    Setting::put('is_registration_open', '0');

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/registration-closed')
            ->where('status', 'closed')
            ->where('closedAt', null));
});

test('the registration screen shows the not open page before registration opens', function () {
    Setting::put('registration_opens_at', '2026-09-28');
    $this->travelTo(CarbonImmutable::parse('2026-09-27 23:59:59', Setting::EVENT_TIMEZONE));

    $this->get(route('register'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/registration-closed')
            ->where('status', 'not_open')
            ->where('opensAt', '2026-09-28T00:00:00+07:00'));
});

test('the registration screen stays open until the end of the deadline day in WIB', function () {
    Setting::put('registration_opens_at', '2026-09-28');
    Setting::put('registration_deadline', '2026-10-19');
    $this->travelTo(CarbonImmutable::parse('2026-10-19 23:59:59', Setting::EVENT_TIMEZONE));

    $this->get(route('register'))
        ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
});

test('the registration screen shows the closed page after the deadline', function () {
    Setting::put('registration_deadline', '2026-10-19');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 00:00:00', Setting::EVENT_TIMEZONE));

    $this->get(route('register'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/registration-closed')
            ->where('status', 'closed')
            ->where('closedAt', '2026-10-19T23:59:59+07:00'));
});

test('registration is rejected while registration is closed', function (string $key, string $value, string $message) {
    Setting::put($key, $value);
    $this->travelTo(CarbonImmutable::parse('2026-10-20 00:00:00', Setting::EVENT_TIMEZONE));
    $category = AwardCategory::factory()->create();

    $this->post(route('register.store'), registrationPayload($category))
        ->assertSessionHasErrors(['registration' => $message]);

    $this->assertGuest();
    expect(User::count())->toBe(0)
        ->and(Submission::count())->toBe(0);
})->with([
    'closed by admin' => ['is_registration_open', '0', 'Registration is closed.'],
    'not open yet' => ['registration_opens_at', '2026-10-21', 'Registration is not open yet.'],
    'past the deadline' => ['registration_deadline', '2026-10-19', 'Registration is closed.'],
]);
