<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\Actions\ConsumeArmedScenario;
use He4rt\FakeBinance\Scenarios\Actions\DisarmScenario;
use He4rt\FakeBinance\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;

it('arms an outcome with its payload', function (): void {
    $armed = (new ArmScenario)->handle(
        SpotConversionOutcome::FillPartialExpired,
        new ArmedScenarioPayload(fraction: '0.25'),
    );

    expect($armed->leg)->toBe(VenueLeg::SpotConversion)
        ->and($armed->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired)
        ->and($armed->payload->fraction)->toBe('0.25')
        ->and($armed->armed_at)->not->toBeNull();
});

it('replaces the armed scenario of a leg instead of stacking a second one', function (): void {
    $arm = new ArmScenario;

    $arm->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.25'));
    $arm->handle(SpotConversionOutcome::RespondRejected);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected);
});

it('arms the same leg twice in a row without ever violating the leg unique index', function (): void {
    $arm = new ArmScenario;

    $first = $arm->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.25'));
    $second = $arm->handle(SpotConversionOutcome::RespondRejected);

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and(ArmedScenario::query()->sole()->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected);
});

it('disarms a leg, leaving nothing behind', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    (new DisarmScenario)->handle(VenueLeg::SpotConversion);

    expect(ArmedScenario::query()->count())->toBe(0);
});

it('reads the armed scenario without consuming it', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    $read = (new GetArmedScenario)->handle(VenueLeg::SpotConversion);

    expect($read)->not->toBeNull()
        ->and(ArmedScenario::query()->count())->toBe(1);
});

it('consumes the armed scenario exactly once — the next read finds nothing', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);
    $consume = new ConsumeArmedScenario;

    $first = $consume->handle(VenueLeg::SpotConversion);
    $second = $consume->handle(VenueLeg::SpotConversion);

    expect($first)->not->toBeNull()
        ->and($first->resolvedOutcome())->toBe(SpotConversionOutcome::RespondRejected)
        ->and($second)->toBeNull()
        ->and(ArmedScenario::query()->count())->toBe(0);
});

it('gives back null when the leg was never armed', function (): void {
    expect((new ConsumeArmedScenario)->handle(VenueLeg::SpotConversion))->toBeNull()
        ->and((new GetArmedScenario)->handle(VenueLeg::SpotConversion))->toBeNull();
});
