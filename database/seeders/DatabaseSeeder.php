<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public const string SUPERADMIN_EMAIL = 'Sadmin0@icsa.com';

    public const string SUPERADMIN_PASSWORD = 'ueRAnis-aY!o{Z8R';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            AwardCategorySeeder::class,
            AssessmentTemplateSeeder::class,
        ]);

        $this->seedSuperadmin();
    }

    /**
     * Create the initial superadmin.
     *
     * An existing account with this email is left untouched, so re-seeding never resets a changed password.
     * Change the password from the profile settings after the first login.
     */
    private function seedSuperadmin(): void
    {
        $superadmin = User::query()->firstOrNew(['email' => self::SUPERADMIN_EMAIL]);

        if ($superadmin->exists) {
            return;
        }

        $superadmin->forceFill([
            'name' => 'Superadmin',
            'password' => Hash::make(self::SUPERADMIN_PASSWORD),
            'role' => UserRole::Superadmin,
            'position' => 'Superadmin',
            'email_verified_at' => now(),
        ])->save();
    }
}
