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

        if (! User::query()->where('email', 'admin@app.com')->exists()) {
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@app.com',
                'password' => Hash::make('123123123'),
                'role' => 'superadmin',
                'position' => 'Superadmin',
                'phone' => '081234567890',
            ]);
        }
    }
}
