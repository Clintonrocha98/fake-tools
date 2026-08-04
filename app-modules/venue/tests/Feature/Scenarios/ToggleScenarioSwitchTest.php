<?php

declare(strict_types=1);

use He4rt\Venue\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\Venue\Scenarios\Actions\ToggleScenarioSwitch;
use He4rt\Venue\Scenarios\Enums\ScenarioSwitch;
use He4rt\Venue\Scenarios\Models\ScenarioSwitchboard;

it('creates the singleton row on the first read, everything off', function (): void {
    expect(ScenarioSwitchboard::query()->count())->toBe(0);

    $switchboard = (new GetScenarioSwitchboard)();

    expect($switchboard->outage_mode)->toBeFalse()
        ->and($switchboard->rate_limit_mode)->toBeFalse()
        ->and($switchboard->clock_skew_mode)->toBeFalse();
    expect(ScenarioSwitchboard::query()->count())->toBe(1);
});

it('never creates a second row across repeated reads', function (): void {
    (new GetScenarioSwitchboard)();
    (new GetScenarioSwitchboard)();

    expect(ScenarioSwitchboard::query()->count())->toBe(1);
});

it('toggles each switch independently', function (ScenarioSwitch $switch, string $column): void {
    $toggle = new ToggleScenarioSwitch;

    $on = $toggle($switch, true);
    expect($on->{$column})->toBeTrue();

    $off = $toggle($switch, false);
    expect($off->{$column})->toBeFalse();
})->with([
    'outage' => [ScenarioSwitch::Outage, 'outage_mode'],
    'rate limit' => [ScenarioSwitch::RateLimit, 'rate_limit_mode'],
    'clock skew' => [ScenarioSwitch::ClockSkew, 'clock_skew_mode'],
]);

it('persists the toggle on the same singleton row', function (): void {
    (new ToggleScenarioSwitch)(ScenarioSwitch::Outage, true);

    expect(ScenarioSwitchboard::query()->count())->toBe(1)
        ->and(ScenarioSwitchboard::query()->first()->outage_mode)->toBeTrue();
});
