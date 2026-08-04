<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\CreatesApplication;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Seeded once per process by RefreshDatabase (forwarded to migrate:fresh
     * --seeder), committed before the per-test transaction — so the RBAC
     * baseline is visible to every test without a per-test rebuild.
     *
     * @var class-string<Seeder>
     */
    protected string $seeder = PermissionsSeeder::class;
}
