<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('log viewer is accessible outside production', function (): void {
    get(route('log-viewer.index'))
        ->assertOk();
});

test('log viewer is blocked in production without an authorization gate', function (): void {
    app()->instance('env', 'production');

    get(route('log-viewer.index'))
        ->assertForbidden();
});
