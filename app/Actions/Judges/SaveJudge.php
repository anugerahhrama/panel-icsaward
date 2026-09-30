<?php

namespace App\Actions\Judges;

use App\Enums\UserRole;
use App\Models\Judge;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SaveJudge
{
    /**
     * Save a judge profile, its login account and its category assignments.
     *
     * A new photo is stored before the transaction and removed again if it fails; the
     * replaced or removed photo is only deleted once the transaction has committed.
     *
     * @param  array{name: string, position: string, institution: string|null, bio: string|null, show_on_landing: bool, landing_category_id: int|null, sort_order: int}  $profile
     * @param  array{email: string, password: string|null}|null  $account
     * @param  array<int, array{is_recused: bool}>  $assignments
     */
    public function handle(Judge $judge, array $profile, ?array $account, array $assignments, ?UploadedFile $photo = null, bool $removePhoto = false): Judge
    {
        $previousPhoto = $judge->photo_path;
        $storedPhoto = null;

        if ($photo !== null) {
            $storedPhoto = $photo->store('judges', Judge::PHOTO_DISK);

            if ($storedPhoto === false) {
                throw new RuntimeException('Unable to store the judge photo.');
            }
        }

        try {
            DB::transaction(function () use ($judge, $profile, $account, $assignments, $storedPhoto, $removePhoto): void {
                $judge->fill($profile);

                if ($storedPhoto !== null || $removePhoto) {
                    $judge->photo_path = $storedPhoto;
                }

                if ($account !== null) {
                    $this->saveAccount($judge, $profile, $account);
                }

                $judge->save();
                $this->ensureScoredAssignmentsAreKept($judge, $assignments);
                $judge->categories()->sync($assignments);
            });
        } catch (Throwable $exception) {
            if ($storedPhoto !== null) {
                Storage::disk(Judge::PHOTO_DISK)->delete($storedPhoto);
            }

            throw $exception;
        }

        if ($previousPhoto !== null && $previousPhoto !== $judge->photo_path) {
            Storage::disk(Judge::PHOTO_DISK)->delete($previousPhoto);
        }

        return $judge;
    }

    /**
     * Refuse to unassign the judge from a category they have already scored; recusing stays possible.
     *
     * @param  array<int, array{is_recused: bool}>  $assignments
     */
    private function ensureScoredAssignmentsAreKept(Judge $judge, array $assignments): void
    {
        if (! $judge->wasRecentlyCreated && $judge->scoredCategoryIds()->diff(array_keys($assignments))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => 'This judge has already scored a removed category. Mark them as recused instead.',
            ]);
        }
    }

    /**
     * Create or update the judge's login account, keeping an encrypted copy of any password
     * the admin sets so a superadmin can look it up later.
     *
     * @param  array{name: string, position: string, institution: string|null}  $profile
     * @param  array{email: string, password: string|null}  $account
     */
    private function saveAccount(Judge $judge, array $profile, array $account): void
    {
        $user = $judge->user ?? new User;

        $user->fill([
            'name' => $profile['name'],
            'email' => $account['email'],
            'position' => $profile['position'],
            'company_name' => $profile['institution'],
        ]);

        if (! $user->exists) {
            $user->role = UserRole::Judge;
            $user->email_verified_at = now();
        }

        if ($account['password'] !== null) {
            $user->password = $account['password'];
            $judge->account_password = $account['password'];
        }

        $user->save();
        $judge->user()->associate($user);
    }
}
