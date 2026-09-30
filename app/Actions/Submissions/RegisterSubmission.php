<?php

namespace App\Actions\Submissions;

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

            $maxRegistrations = max(1, (int) Setting::get('max_registrations_per_user', '1'));

            if ($user->submissions()->count() >= $maxRegistrations) {
                throw ValidationException::withMessages([
                    'award_category_id' => trans_choice(
                        'You can register at most :count initiative.|You can register at most :count initiatives.',
                        $maxRegistrations,
                    ),
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
}
