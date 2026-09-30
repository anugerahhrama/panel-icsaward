<?php

namespace App\Actions\Accounts;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EnsureSuperadminRemains
{
    /**
     * Refuse to remove the superadmin role from the given account when it is the last active
     * superadmin. Must run inside a transaction: every superadmin row stays locked until commit,
     * so two superadmins cannot remove each other at the same time.
     */
    public function handle(User $account, string $errorKey): void
    {
        $superadminIds = User::query()
            ->where('role', UserRole::Superadmin)
            ->lockForUpdate()
            ->pluck('id');

        if ($superadminIds->reject(fn (int $id): bool => $id === $account->id)->isEmpty()) {
            throw ValidationException::withMessages([
                $errorKey => 'At least one active superadmin account must remain.',
            ]);
        }
    }
}
