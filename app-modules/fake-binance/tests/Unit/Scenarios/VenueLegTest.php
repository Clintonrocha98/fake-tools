<?php

declare(strict_types=1);

use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;

it('lists the outcomes of the spot leg', function (): void {
    expect(VenueLeg::SpotConversion->outcomes())->toBe(SpotConversionOutcome::cases());
});

it('resolves a stored outcome value back into the leg enum', function (): void {
    expect(VenueLeg::SpotConversion->outcomeFrom('fill_partial_expired'))
        ->toBe(SpotConversionOutcome::FillPartialExpired);
});

it('points every outcome back at its own leg', function (LegOutcomeContract $outcome): void {
    expect($outcome->leg())->toBe(VenueLeg::SpotConversion);
})->with(SpotConversionOutcome::cases());

it('declares which payload field each outcome uses', function (): void {
    expect(SpotConversionOutcome::FillPartialExpired->payloadFields())->toBe(['fraction'])
        ->and(SpotConversionOutcome::RefuseWithCode->payloadFields())->toBe(['errorCode'])
        ->and(SpotConversionOutcome::RespondRejected->payloadFields())->toBe([])
        ->and(SpotConversionOutcome::EmitUnknownStatus->payloadFields())->toBe(['rawStatus']);
});

it('gives every outcome a label, a description and a color', function (LegOutcomeContract $outcome): void {
    expect($outcome->getLabel())->not->toBe('')
        ->and($outcome->getDescription())->not->toBe('')
        ->and($outcome->getColor())->not->toBeEmpty();
})->with(SpotConversionOutcome::cases());
