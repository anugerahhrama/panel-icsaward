<?php

use App\Enums\UserRole;
use App\Models\Judge;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('the database seeder creates the superadmin, without dummy data, and can run again', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $superadmin = User::query()->sole();

    expect($superadmin->email)->toBe(DatabaseSeeder::SUPERADMIN_EMAIL)
        ->and($superadmin->role)->toBe(UserRole::Superadmin)
        ->and($superadmin->email_verified_at)->not->toBeNull()
        ->and(Hash::check(DatabaseSeeder::SUPERADMIN_PASSWORD, $superadmin->password))->toBeTrue()
        ->and($superadmin->account_password)->toBeNull()
        ->and(Submission::query()->count())->toBe(0)
        ->and(Judge::query()->count())->toBe(0);
});

test('the database seeder leaves an existing superadmin untouched', function () {
    $superadmin = User::factory()->superadmin()->create([
        'email' => DatabaseSeeder::SUPERADMIN_EMAIL,
        'password' => 'changed-later-password',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('changed-later-password', $superadmin->fresh()->password))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});
