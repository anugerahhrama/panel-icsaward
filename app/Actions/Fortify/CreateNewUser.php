<?php

namespace App\Actions\Fortify;

use App\Actions\Submissions\RegisterSubmission;
use App\Concerns\RegistrationValidationRules;
use App\Models\AwardCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use RegistrationValidationRules;

    public function __construct(private RegisterSubmission $registerSubmission) {}

    /**
     * Validate and create a newly registered participant together with their first submission.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $this->ensureRegistrationIsOpen();

        $validated = Validator::make($input, [
            ...$this->accountRules(),
            ...$this->initiativeRules(),
            ...$this->termsRules(),
        ], $this->registrationMessages())->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'position' => $validated['position'],
                'company_name' => $validated['company_name'],
                'password' => $validated['password'],
            ]);

            $this->registerSubmission->handle(
                $user,
                AwardCategory::query()->whereKey($validated['award_category_id'])->firstOrFail(),
                [
                    'initiative_title' => $validated['initiative_title'],
                    'initiative_description' => $validated['initiative_description'],
                ],
            );

            return $user;
        });
    }
}
