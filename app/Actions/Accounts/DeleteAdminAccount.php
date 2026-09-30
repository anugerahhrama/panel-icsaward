<?php

namespace App\Actions\Accounts;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteAdminAccount
{
    public function __construct(private EnsureSuperadminRemains $ensureSuperadminRemains) {}

    /**
     * Soft delete an admin or superadmin account. The account can no longer sign in (the user
     * provider skips trashed users, which also ends its open sessions), while the records it
     * reviewed or confirmed keep pointing at it.
     */
    public function handle(User $actingUser, User $account): void
    {
        if ($account->is($actingUser)) {
            throw ValidationException::withMessages([
                'account' => 'You cannot delete your own account.',
            ]);
        }

        DB::transaction(function () use ($account): void {
            if ($account->role === UserRole::Superadmin) {
                $this->ensureSuperadminRemains->handle($account, 'account');
            }

            $account->delete();
        });
    }
}
