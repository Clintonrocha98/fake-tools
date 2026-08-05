<?php

declare(strict_types=1);

it('returns the documented bookTicker shape as decimal strings, public and unsigned', function (): void {
    config(['venue-spot.usdcbrl.price' => '5.10', 'venue-spot.usdcbrl.spread' => '0.02']);

    $response = $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL');

    $response->assertOk()->assertExactJson([
        'symbol' => 'USDCBRL',
        'bidPrice' => '5.09',
        'bidQty' => '10',
        'askPrice' => '5.11',
        'askQty' => '10',
    ]);
});

it('derives bid/ask from a different configured price and spread', function (): void {
    config(['venue-spot.usdcbrl.price' => '5.00', 'venue-spot.usdcbrl.spread' => '0.10']);

    $response = $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL');

    $response->assertOk()->assertJson([
        'bidPrice' => '4.95',
        'askPrice' => '5.05',
    ]);
});

it('refuses an unknown symbol with -1121', function (): void {
    $response = $this->getJson('/api/v3/ticker/bookTicker?symbol=BTCBRL');

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});

it('refuses a missing symbol with -1121', function (): void {
    $response = $this->getJson('/api/v3/ticker/bookTicker');

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});
