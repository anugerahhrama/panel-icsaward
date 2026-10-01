<?php

namespace App\Actions\Accounts;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveAdminAccount
{
    public function __construct(private EnsureSuperadminRemains $ensureSuperadminRemains) {}

    /**
     * Create or update an admin or superadmin account. New accounts are verified right away;
     * the password is only replaced when a new one is given, keeping an encrypted copy
     * that superadmins can view again.
     *
     * @param  array{name: string, email: string, position: string|null, phone: string|null, role: UserRole, password: string|null}  $data
     */
    public function handle(User $actingUser, User $account, array $data): User
    {
        return DB::transaction(function () use ($actingUser, $account, $data): User {
            if ($account->exists && $account->role !== $data['role']) {
                if ($account->is($actingUser)) {
                    throw ValidationException::withMessages([
                        'role' => 'You cannot change your own role.',
                    ]);
                }

                if ($account->role === UserRole::Superadmin) {
                    $this->ensureSuperadminRemains->handle($account, 'role');
                }
            }

            $account->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'position' => $data['position'],
                'phone' => $data['phone'],
            ]);
            $account->role = $data['role'];

            if (! $account->exists) {
                $account->email_verified_at = now();
            }

            if ($data['password'] !== null) {
                $account->password = $data['password'];
                $account->account_password = $data['password'];
            }

            $account->save();

            return $account;
        });
    }
}
