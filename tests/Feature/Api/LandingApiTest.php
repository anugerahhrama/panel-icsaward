<?php

use App\Enums\SubmissionStatus;
use App\Http\Middleware\EnsureLandingApiToken;
use App\Models\AwardCategory;
use App\Models\Judge;
use App\Models\Setting;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;

const LANDING_API_TOKEN = 'landing-token-for-tests';

beforeEach(function () {
    Setting::putEncrypted(EnsureLandingApiToken::SETTING_KEY, LANDING_API_TOKEN);
});

test('requests without the current token are rejected', function (?string $token) {
    $headers = $token === null ? [] : ['Authorization' => "Bearer {$token}"];

    $this->getJson(route('api.v1.landing.categories'), $headers)
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
})->with([
    'missing token' => [null],
    'wrong token' => ['not-the-token'],
]);

test('the api is disabled until a token is generated', function () {
    Setting::putEncrypted(EnsureLandingApiToken::SETTING_KEY, null);

    $this->getJson(route('api.v1.landing.categories'), ['Authorization' => 'Bearer '])
        ->assertUnauthorized();
});

test('categories are listed in display order', function () {
    $second = AwardCategory::factory()->individual()->create(['name' => 'Best Leader', 'description' => 'Leaders.', 'sort_order' => 2]);
    $first = AwardCategory::factory()->create(['name' => 'Best Energy', 'description' => null, 'sort_order' => 1]);

    $this->withToken(LANDING_API_TOKEN)
        ->getJson(route('api.v1.landing.categories'))
        ->assertExactJson(['data' => [
            ['id' => $first->id, 'name' => 'Best Energy', 'description' => null, 'applicant_type' => 'organization', 'sort_order' => 1],
            ['id' => $second->id, 'name' => 'Best Leader', 'description' => 'Leaders.', 'applicant_type' => 'individual', 'sort_order' => 2],
        ]]);
});

test('only judges shown on the landing page are listed, without account or assignment data', function () {
    Storage::fake(Judge::PHOTO_DISK);
    $category = AwardCategory::factory()->create();
    $judge = Judge::factory()->withAccount()->assignedTo([$category->id], recused: true)->create([
        'name' => 'Dr. Jane',
        'position' => 'Director',
        'institution' => 'IBCSD',
        'bio' => 'Bio.',
        'photo_path' => 'judges/jane.webp',
        'show_on_landing' => true,
        'landing_category_id' => $category->id,
        'sort_order' => 3,
    ]);
    Judge::factory()->create(['show_on_landing' => false]);

    $response = $this->withToken(LANDING_API_TOKEN)->getJson(route('api.v1.landing.judges'));

    expect($response->json('data.0.photo_url'))->toBeUrl();
    $response->assertExactJson(['data' => [[
        'id' => $judge->id,
        'name' => 'Dr. Jane',
        'position' => 'Director',
        'institution' => 'IBCSD',
        'bio' => 'Bio.',
        'photo_url' => url('storage/judges/jane.webp'),
        'landing_category_id' => $category->id,
        'sort_order' => 3,
    ]]]);
});

test('stats count every registration, including disqualified ones, per category', function () {
    $energy = AwardCategory::factory()->create(['sort_order' => 1]);
    $leader = AwardCategory::factory()->create(['sort_order' => 2]);
    $empty = AwardCategory::factory()->create(['sort_order' => 3]);
    Submission::factory()->for($energy)->create();
    Submission::factory()->for($energy)->paperSubmitted()->create();
    Submission::factory()->for($leader)->paperSubmitted()->create(['status' => SubmissionStatus::Disqualified]);
    $this->freezeTime();

    $response = $this->withToken(LANDING_API_TOKEN)->getJson(route('api.v1.landing.stats'));

    $response->assertExactJson(['data' => [
        'registered' => 3,
        'papers_submitted' => 2,
        'by_category' => [
            ['category_id' => $energy->id, 'registered' => 2, 'papers_submitted' => 1],
            ['category_id' => $leader->id, 'registered' => 1, 'papers_submitted' => 1],
            ['category_id' => $empty->id, 'registered' => 0, 'papers_submitted' => 0],
        ],
        'updated_at' => now()->toIso8601String(),
    ]]);
});

test('stats are cached for a few minutes', function () {
    $category = AwardCategory::factory()->create();
    $this->withToken(LANDING_API_TOKEN)->getJson(route('api.v1.landing.stats'))->assertJsonPath('data.registered', 0);
    Submission::factory()->for($category)->create();

    $this->withToken(LANDING_API_TOKEN)->getJson(route('api.v1.landing.stats'))->assertJsonPath('data.registered', 0);

    $this->travel(6)->minutes();
    $this->withToken(LANDING_API_TOKEN)->getJson(route('api.v1.landing.stats'))->assertJsonPath('data.registered', 1);
});

test('the api is limited to 60 requests per minute', function () {
    foreach (range(1, 60) as $attempt) {
        $this->withToken(LANDING_API_TOKEN)->getJson(route('api.v1.landing.categories'))->assertOk();
    }

    $this->withToken(LANDING_API_TOKEN)->getJson(route('api.v1.landing.categories'))->assertTooManyRequests();
});
