<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;

/**
 * `DatabaseSeeder::spawnAdminUser()` only runs under `app()->isLocal()`
 * (mirrors the container's `APP_ENV=local`), so the suite forces that
 * environment rather than exercising it under `testing`.
 */
function seedAsLocalEnvironment(): void
{
    app()['env'] = 'local';

    test()->seed(DatabaseSeeder::class);
}

it('creates the admin user without relying on the UserFactory/Faker', function (): void {
    seedAsLocalEnvironment();

    $admin = User::query()->where('email', 'admin@admin.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('admin')
        ->and($admin->hasRole(Roles::SuperAdmin))->toBeTrue();
});

it('is idempotent when the admin user already exists', function (): void {
    seedAsLocalEnvironment();
    seedAsLocalEnvironment();

    expect(User::query()->where('email', 'admin@admin.com')->count())->toBe(1);
});
