<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case Participant = 'participant';
    case Admin = 'admin';
    case Judge = 'judge';

    /**
     * The roles that sign in to the admin panel and are managed from Admin Accounts.
     *
     * @return array<int, self>
     */
    public static function adminRoles(): array
    {
        return [self::Superadmin, self::Admin];
    }

    public function isAdmin(): bool
    {
        return in_array($this, self::adminRoles(), true);
    }
}
