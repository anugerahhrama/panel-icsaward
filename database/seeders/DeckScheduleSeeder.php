<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class DeckScheduleSeeder extends Seeder
{
    /**
     * The competition schedule from the timeline slide of the concept deck (update 2026-09-30).
     *
     * @var array<string, string>
     */
    public const array SCHEDULE = [
        'registration_opens_at' => '2026-10-01',
        'registration_deadline' => '2026-10-26',
        'paper_deadline' => '2026-10-26',
        'timeline_administrative_selection' => '1 – 26 October 2026',
        'timeline_desk_evaluation' => '12 – 30 October 2026',
        'timeline_finalists_announcement' => '2 – 4 November 2026',
        'timeline_pitching' => '6 – 10 November 2026',
        'timeline_awarding_night' => '20 November 2026 · Mason Pine Hotel, Kota Baru Parahyangan, Bandung',
    ];

    /**
     * Overwrite the schedule settings with the deck values.
     *
     * Unlike `SettingSeeder`, this replaces values already in the database. It is run manually
     * (`php artisan db:seed --class=DeckScheduleSeeder`) and is not part of `DatabaseSeeder`.
     */
    public function run(): void
    {
        foreach (self::SCHEDULE as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
