<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Database\QueryException;

it('round-trips the payload through the typed cast', function (): void {
    $scenario = ArmedScenario::factory()->create([
        'payload' => new ArmedScenarioPayload(fraction: '0.25'),
    ]);

    expect($scenario->refresh()->payload)->toBeInstanceOf(ArmedScenarioPayload::class)
        ->and($scenario->payload->fraction)->toBe('0.25');
});

it('accepts a raw array on the payload, the legacy assignment branch', function (): void {
    $scenario = ArmedScenario::factory()->create(['payload' => ['rawStatus' => 'BANANA']]);

    expect($scenario->refresh()->payload->rawStatus)->toBe('BANANA');
});

it('resolves the stored outcome back into the leg enum', function (): void {
    $scenario = ArmedScenario::factory()->spotPartial()->create();

    expect($scenario->resolvedOutcome())->toBe(SpotConversionOutcome::FillPartialExpired)
        ->and($scenario->leg)->toBe(VenueLeg::SpotConversion);
});

it('refuses a second armed scenario for the same leg at the database level', function (): void {
    ArmedScenario::factory()->spotPartial()->create();

    ArmedScenario::factory()->spotPartial()->create();
})->throws(QueryException::class);
