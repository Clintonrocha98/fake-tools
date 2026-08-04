<?php

declare(strict_types=1);

namespace Database\Seeders;

use He4rt\Identity\Permissions\Roles;
use He4rt\Identity\Users\User;
use He4rt\Venue\Database\Seeders\LedgerAccountSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->syncPermissions();
        $this->call(LedgerAccountSeeder::class);

        if (app()->isLocal()) {
            $this->spawnAdminUser();
        }

        $this->output('Database seeding completed successfully. Have fun!');
    }

    /**
     * Cria o admin sem passar pela UserFactory: `fakerphp/faker` é dependência
     * de dev e não existe num vendor buildado com `composer install --no-dev`
     * (a imagem Docker deste servidor fake), então o seed automático do
     * container quebraria se dependesse de `fake()`.
     */
    public function spawnAdminUser(): void
    {
        $this->output('Creating admin user...');

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'locale' => 'en',
                'theme_color' => '#4f46e5',
            ],
        );

        if (!$admin->hasRole(Roles::SuperAdmin)) {
            $admin->assignRole(Roles::SuperAdmin);
        }

        $this->output('Admin user created successfully.');
    }

    private function syncPermissions(): void
    {
        $this->output('Syncing permissions...');
        $this->call(PermissionsSeeder::class);
        $this->output('Permissions synced successfully.');
    }

    private function output(string $message): void
    {
        $this->command->getOutput()->block(
            messages: [sprintf('<fg=white;bg=blue> SEEDER </> <fg=white>%s</>', $message)],
            prefix: '  ',
            escape: false,
        );
    }
}
