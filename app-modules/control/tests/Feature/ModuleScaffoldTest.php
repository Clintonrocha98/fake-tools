<?php

declare(strict_types=1);

use He4rt\Control\Feed\Models\ControlEvent;
use Illuminate\Support\Facades\Schema;

it('registra o provider e resolve a config do módulo', function (): void {
    expect(config('control.enabled'))->toBeTrue()
        ->and(config('control.feed.max_limit'))->toBe(500)
        ->and(config('control.state.recent_limit'))->toBe(10);
});

it('carrega a migration do feed', function (): void {
    expect(Schema::hasTable('control_events'))->toBeTrue()
        ->and(Schema::hasColumns('control_events', [
            'id', 'channel', 'level', 'message', 'context', 'request_id', 'occurred_at',
        ]))->toBeTrue();
});

it('declara a conexão dedicada do feed', function (): void {
    expect(config('database.connections.control'))->toBeArray()
        ->and(config('database.connections.control.driver'))->toBe('pgsql');
});

it('poda o feed por idade, não por contagem', function (): void {
    config(['control.feed.retention_hours' => 24]);

    ControlEvent::factory()->create(['occurred_at' => now()->subHours(25)]);
    $recente = ControlEvent::factory()->create(['occurred_at' => now()->subHour()]);

    new ControlEvent()->prunable()->delete();

    expect(ControlEvent::query()->pluck('id')->all())->toBe([$recente->id]);
});
