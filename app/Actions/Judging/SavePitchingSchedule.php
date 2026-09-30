<?php

namespace App\Actions\Judging;

use App\Models\AwardCategory;
use App\Models\PitchingSession;
use App\Models\PitchingSlot;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavePitchingSchedule
{
    /**
     * Save a category's pitching session and its finalists' time slots; dates and times are read as WIB.
     *
     * Slots are synced by submission: a finalist without a start time loses their slot.
     *
     * @param  array{date: string, start_time: string, location: string|null, meeting_link: string|null, slots: list<array{submission_id: int, starts_at: string|null}>}  $schedule
     *
     * @throws ValidationException
     */
    public function handle(AwardCategory $category, array $schedule): PitchingSession
    {
        return DB::transaction(function () use ($category, $schedule): PitchingSession {
            $category = AwardCategory::query()->lockForUpdate()->findOrFail($category->id);

            if (! $category->isFinalistsConfirmed()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Confirm the finalists of this category before scheduling their pitching.',
                ]);
            }

            $submissionIds = array_column($schedule['slots'], 'submission_id');

            $finalistCount = $category->finalists()->whereKey($submissionIds)->count();

            if ($finalistCount !== count($submissionIds)) {
                throw ValidationException::withMessages([
                    'slots' => 'Only the confirmed finalists of this category can be scheduled.',
                ]);
            }

            $session = PitchingSession::query()->firstOrNew(['award_category_id' => $category->id]);
            $session->fill([
                'scheduled_at' => $this->wibDateTime($schedule['date'], $schedule['start_time']),
                'location' => $schedule['location'],
                'meeting_link' => $schedule['meeting_link'],
            ])->save();

            $scheduled = array_filter($schedule['slots'], fn (array $slot): bool => $slot['starts_at'] !== null);

            PitchingSlot::query()
                ->where('pitching_session_id', $session->id)
                ->whereNotIn('submission_id', array_column($scheduled, 'submission_id'))
                ->delete();

            foreach ($scheduled as $slot) {
                $session->slots()->updateOrCreate(
                    ['submission_id' => $slot['submission_id']],
                    ['starts_at' => $this->wibDateTime($schedule['date'], (string) $slot['starts_at'])],
                );
            }

            return $session;
        });
    }

    /**
     * Read a WIB date and time entered by the committee, in the app timezone for storage.
     */
    private function wibDateTime(string $date, string $time): CarbonImmutable
    {
        $dateTime = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", Setting::EVENT_TIMEZONE)
            ?: throw ValidationException::withMessages(['date' => 'The date is invalid.']);

        return $dateTime->setTimezone(config('app.timezone'));
    }
}
