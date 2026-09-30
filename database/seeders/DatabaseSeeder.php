<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $superadmin = User::query()->firstOrNew(['email' => 'superadmin@app.com']);

        if (! $superadmin->exists) {
            $superadmin->forceFill([
                'name' => 'Superadmin',
                'password' => Hash::make('123123123'),
                'role' => 'superadmin',
                'position' => 'Superadmin',
                'phone' => '081234567890',
                'email_verified_at' => now(),
            ])->save();
        }
    }
}
