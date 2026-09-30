<?php

use App\Models\Setting;
use Database\Seeders\DeckScheduleSeeder;

test('the deck schedule seeder overwrites existing schedule settings and leaves other settings untouched', function () {
    Setting::query()->create(['key' => 'registration_deadline', 'value' => '2026-10-19']);
    Setting::query()->create(['key' => 'timeline_awarding_night', 'value' => '21 November 2026']);
    Setting::query()->create(['key' => 'contact_email', 'value' => 'team@example.com']);

    $this->seed(DeckScheduleSeeder::class);
    $this->seed(DeckScheduleSeeder::class);

    expect(Setting::query()->whereIn('key', array_keys(DeckScheduleSeeder::SCHEDULE))->pluck('value', 'key')->all())
        ->toEqualCanonicalizing(DeckScheduleSeeder::SCHEDULE)
        ->and(Setting::query()->where('key', 'contact_email')->value('value'))->toBe('team@example.com')
        ->and(Setting::query()->count())->toBe(count(DeckScheduleSeeder::SCHEDULE) + 1);
});
