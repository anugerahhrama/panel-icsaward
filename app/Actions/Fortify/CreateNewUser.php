<?php

namespace App\Actions\Fortify;

use App\Actions\Submissions\RegisterSubmission;
use App\Actions\Submissions\SendSubmissionConfirmation;
use App\Concerns\RegistrationValidationRules;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Models\AwardCategory;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use RegistrationValidationRules;

    public function __construct(
        private RegisterSubmission $registerSubmission,
        private SendSubmissionConfirmation $sendSubmissionConfirmation,
    ) {}

    /**
     * Validate and create a newly registered participant together with their first submission.
     *
     * A participant signing up with Google gets a random password (they can set one through
     * "Forgot password") and a verified email. Fortify's verification listener skips verified
     * users, so the confirmation email carrying the submission link is queued here instead.
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

        $googleSignup = $this->googleSignup();

        /** @var array{User, Submission} $registration */
        $registration = DB::transaction(function () use ($validated, $googleSignup): array {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'position' => $validated['position'],
                'company_name' => $validated['company_name'],
                'password' => $googleSignup === null ? $validated['password'] : Str::password(32),
            ]);

            if ($googleSignup !== null) {
                $user->email_verified_at = now();
            }

            $user->save();

            $submission = $this->registerSubmission->handle(
                $user,
                AwardCategory::query()->whereKey($validated['award_category_id'])->firstOrFail(),
                [
                    'initiative_title' => $validated['initiative_title'],
                    'initiative_description' => $validated['initiative_description'],
                ],
            );

            return [$user, $submission];
        });

        [$user, $submission] = $registration;

        if ($googleSignup !== null) {
            $this->sendSubmissionConfirmation->handle($submission);
            session()->forget(GoogleLoginController::SIGNUP_SESSION_KEY);
        }

        return $user;
    }
}
