<?php

declare(strict_types=1);

beforeEach(function (): void {
    config(['fake-binance-spot.symbols.USDCBRL.price' => '5.10', 'fake-binance-spot.symbols.USDCBRL.spread' => '0.02']);
});

it('returns the configured mid without spread, public and unsigned', function (): void {
    $response = $this->getJson('/api/v3/ticker/price?symbol=USDCBRL');

    $response->assertOk()->assertExactJson([
        'symbol' => 'USDCBRL',
        'price' => '5.1',
    ]);
});

it('serves USDTBRL with its own configured mid', function (): void {
    config(['fake-binance-spot.symbols.USDTBRL.price' => '5.25']);

    $response = $this->getJson('/api/v3/ticker/price?symbol=USDTBRL');

    $response->assertOk()->assertExactJson([
        'symbol' => 'USDTBRL',
        'price' => '5.25',
    ]);
});

it('refuses an unknown symbol with -1121', function (): void {
    $response = $this->getJson('/api/v3/ticker/price?symbol=BTCBRL');

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});

it('refuses a missing symbol with -1121', function (): void {
    $response = $this->getJson('/api/v3/ticker/price');

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});
