<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('the database seeder creates a single superadmin and can run again', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $superadmin = User::query()->where('email', 'superadmin@app.com')->sole();

    expect($superadmin->role)->toBe(UserRole::Superadmin)
        ->and($superadmin->email_verified_at)->not->toBeNull()
        ->and(User::query()->count())->toBe(1);
});
