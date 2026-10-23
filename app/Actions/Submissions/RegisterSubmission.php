<?php

namespace App\Actions\Submissions;

use App\Enums\RegistrationStatus;
use App\Models\AwardCategory;
use App\Models\Setting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterSubmission
{
    /**
     * Register an initiative for the user in the given award category.
     *
     * The user row is locked for the duration of the transaction so two concurrent
     * registrations cannot both slip under the per-user limit; the unique index on
     * (user_id, award_category_id) remains the last line of defence for duplicates.
     *
     * @param  array{initiative_title: string, initiative_description: string}  $initiative
     *
     * @throws ValidationException
     */
    public function handle(User $user, AwardCategory $category, array $initiative): Submission
    {
        return DB::transaction(function () use ($user, $category, $initiative): Submission {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($user->submissions()->where('award_category_id', $category->id)->exists()) {
                throw ValidationException::withMessages([
                    'award_category_id' => 'You have already registered an initiative in this category.',
                ]);
            }

            if ($user->submissions()->count() >= self::maxRegistrations()) {
                throw ValidationException::withMessages([
                    'award_category_id' => self::limitMessage(),
                ]);
            }

            return $user->submissions()->create([
                'award_category_id' => $category->id,
                'initiative_title' => $initiative['initiative_title'],
                'initiative_description' => $initiative['initiative_description'],
                'terms_accepted_at' => now(),
            ]);
        });
    }

    /**
     * Whether the user may register another initiative right now: registration is open and they are below the limit.
     */
    public static function canRegisterAnother(User $user): bool
    {
        return RegistrationStatus::current()->isOpen()
            && $user->submissions()->count() < self::maxRegistrations();
    }

    /**
     * Get the number of initiatives a single account may register.
     */
    public static function maxRegistrations(): int
    {
        return max(1, (int) Setting::get('max_registrations_per_user', '1'));
    }

    /**
     * Get the message shown when the user has reached the registration limit.
     */
    public static function limitMessage(): string
    {
        return trans_choice(
            'You can register at most :count initiative.|You can register at most :count initiatives.',
            self::maxRegistrations(),
        );
    }
}
