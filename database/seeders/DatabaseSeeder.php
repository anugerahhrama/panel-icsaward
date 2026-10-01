<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
     * Create the initial superadmin from `config('admin.superadmin')`.
     *
     * An existing account with that email is left untouched, so re-seeding never resets a changed password.
     * Missing credentials fail the seed in production and only warn elsewhere.
     */
    private function seedSuperadmin(): void
    {
        /** @var array{name: string|null, email: string|null, password: string|null} $config */
        $config = config('admin.superadmin');

        if (blank($config['email']) || blank($config['password'])) {
            if (app()->isProduction()) {
                throw new RuntimeException('Set SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD before seeding the production database.');
            }

            $this->command->warn('SUPERADMIN_EMAIL / SUPERADMIN_PASSWORD not set: skipped creating the superadmin.');

            return;
        }

        $superadmin = User::query()->firstOrNew(['email' => $config['email']]);

        if ($superadmin->exists) {
            return;
        }

        Validator::make($config, [
            'email' => ['required', 'email'],
            'password' => ['required', Password::defaults()],
        ])->validate();

        $superadmin->forceFill([
            'name' => filled($config['name']) ? $config['name'] : 'Superadmin',
            'password' => Hash::make($config['password']),
            'role' => UserRole::Superadmin,
            'position' => 'Superadmin',
            'email_verified_at' => now(),
        ])->save();
    }
}
