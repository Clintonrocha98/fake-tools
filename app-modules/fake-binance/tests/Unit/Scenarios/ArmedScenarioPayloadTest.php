<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;

it('is empty by default', function (): void {
    $payload = ArmedScenarioPayload::empty();

    expect($payload->fraction)->toBeNull()
        ->and($payload->errorCode)->toBeNull()
        ->and($payload->rawStatus)->toBeNull()
        ->and($payload->reason)->toBeNull()
        ->and($payload->toArray())->toBeEmpty();
});

it('keeps only the fields it knows, dropping anything else', function (): void {
    $payload = ArmedScenarioPayload::fromArray([
        'fraction' => '0.25',
        'errorCode' => -2_010,
        'rawStatus' => 'SOME_FUTURE_STATE',
        'reason' => 'venue said no',
        'somethingElse' => 'ignored',
    ]);

    expect($payload->toArray())->toBe([
        'fraction' => '0.25',
        'errorCode' => -2_010,
        'rawStatus' => 'SOME_FUTURE_STATE',
        'reason' => 'venue said no',
    ]);
});

it('refuses a non-numeric fraction and an empty string, reading them as absent', function (): void {
    $payload = ArmedScenarioPayload::fromArray([
        'fraction' => 'half',
        'rawStatus' => '',
        'reason' => '',
    ]);

    expect($payload->fraction)->toBeNull()
        ->and($payload->rawStatus)->toBeNull()
        ->and($payload->reason)->toBeNull();
});

it('resolves the error code into the Binance enum, and null when the code is unknown', function (): void {
    expect(ArmedScenarioPayload::fromArray(['errorCode' => -2_010])->binanceErrorCode())
        ->toBe(BinanceErrorCode::NewOrderRejected)
        ->and(ArmedScenarioPayload::fromArray(['errorCode' => -99_999])->binanceErrorCode())
        ->toBeNull()
        ->and(ArmedScenarioPayload::empty()->binanceErrorCode())
        ->toBeNull();
});

it('falls back to the given default when no fraction was armed', function (): void {
    expect(ArmedScenarioPayload::fromArray(['fraction' => '0.25'])->fractionOr('0.5'))->toBe('0.25')
        ->and(ArmedScenarioPayload::empty()->fractionOr('0.5'))->toBe('0.5');
});
