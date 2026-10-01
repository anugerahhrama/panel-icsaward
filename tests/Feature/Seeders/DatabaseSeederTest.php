<?php

use App\Enums\UserRole;
use App\Models\Judge;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Run the seeder directly: `db:seed` asks for confirmation in production.
 */
function runDatabaseSeeder(): void
{
    app(DatabaseSeeder::class)->setContainer(app())->__invoke();
}

beforeEach(function () {
    config([
        'admin.superadmin.email' => 'owner@icsaward.test',
        'admin.superadmin.password' => 'S3cure-Passw0rd!',
    ]);
});

test('the database seeder creates the superadmin from config, without dummy data, and can run again', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    $superadmin = User::query()->sole();

    expect($superadmin->email)->toBe('owner@icsaward.test')
        ->and($superadmin->role)->toBe(UserRole::Superadmin)
        ->and($superadmin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('S3cure-Passw0rd!', $superadmin->password))->toBeTrue()
        ->and($superadmin->account_password)->toBeNull()
        ->and(Submission::query()->count())->toBe(0)
        ->and(Judge::query()->count())->toBe(0);
});

test('the database seeder leaves an existing superadmin untouched', function () {
    $superadmin = User::factory()->superadmin()->create([
        'email' => 'owner@icsaward.test',
        'password' => 'changed-later-password',
    ]);

    $this->seed(DatabaseSeeder::class);

    expect(Hash::check('changed-later-password', $superadmin->fresh()->password))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

test('the database seeder skips the superadmin outside production when credentials are missing', function () {
    config(['admin.superadmin.password' => null]);

    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
});

test('the database seeder fails in production when credentials are missing', function () {
    config(['admin.superadmin.email' => null]);
    $this->app['env'] = 'production';

    runDatabaseSeeder();
})->throws(RuntimeException::class, 'SUPERADMIN_EMAIL');

test('the database seeder rejects a weak superadmin password in production', function () {
    config(['admin.superadmin.password' => '123123123']);
    $this->app['env'] = 'production';

    expect(fn () => runDatabaseSeeder())->toThrow(ValidationException::class)
        ->and(User::query()->count())->toBe(0);
});
