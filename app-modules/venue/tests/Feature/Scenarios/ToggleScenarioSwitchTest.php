<?php

declare(strict_types=1);

use He4rt\Venue\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\Venue\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\Venue\Scenarios\Enums\ScenarioSwitch;
use He4rt\Venue\Scenarios\Models\ScenarioSwitchboard;

it('creates the singleton row on the first read, everything off', function (): void {
    expect(ScenarioSwitchboard::query()->count())->toBe(0);

    $switchboard = (new GetScenarioSwitchboard)->handle();

    expect($switchboard->outage_mode)->toBeFalse()
        ->and($switchboard->rate_limit_mode)->toBeFalse()
        ->and($switchboard->clock_skew_mode)->toBeFalse();
    expect(ScenarioSwitchboard::query()->count())->toBe(1);
});

it('never creates a second row across repeated reads', function (): void {
    (new GetScenarioSwitchboard)->handle();
    (new GetScenarioSwitchboard)->handle();

    expect(ScenarioSwitchboard::query()->count())->toBe(1);
});

it('never serves a second switchboard row, even if one exists behind the singleton id', function (): void {
    // Simula o cenário de corrida: uma linha extra, criada com um id qualquer,
    // já existe quando a leitura acontece — a leitura deve sempre pousar na
    // linha do id fixo, nunca numa das outras.
    ScenarioSwitchboard::factory()->create(['outage_mode' => true]);

    $switchboard = (new GetScenarioSwitchboard)->handle();

    expect(ScenarioSwitchboard::query()->count())->toBe(2)
        ->and($switchboard->id)->toBe(GetScenarioSwitchboard::SINGLETON_ID)
        ->and($switchboard->outage_mode)->toBeFalse();
});

it('toggles each switch independently', function (ScenarioSwitch $switch, string $column): void {
    $toggle = new ToggleScenarioSwitch;

    $on = $toggle->handle($switch, enabled: true);
    expect($on->{$column})->toBeTrue();

    $off = $toggle->handle($switch, enabled: false);
    expect($off->{$column})->toBeFalse();
})->with([
    'outage' => [ScenarioSwitch::Outage, 'outage_mode'],
    'rate limit' => [ScenarioSwitch::RateLimit, 'rate_limit_mode'],
    'clock skew' => [ScenarioSwitch::ClockSkew, 'clock_skew_mode'],
]);

it('persists the toggle on the same singleton row', function (): void {
    (new ToggleScenarioSwitch)->handle(ScenarioSwitch::Outage, enabled: true);

    expect(ScenarioSwitchboard::query()->count())->toBe(1)
        ->and(ScenarioSwitchboard::query()->first()->outage_mode)->toBeTrue();
});
