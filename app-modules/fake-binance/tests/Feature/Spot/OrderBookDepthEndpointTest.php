<?php

declare(strict_types=1);

beforeEach(function (): void {
    config([
        'fake-binance-spot.symbols.USDCBRL.price' => '5.10',
        'fake-binance-spot.symbols.USDCBRL.spread' => '0.02',
        'fake-binance-spot.depth.levels' => 3,
        'fake-binance-spot.depth.step' => '0.01',
    ]);
});

it('returns a predictable synthetic book around the bid/ask, public and unsigned', function (): void {
    $response = $this->getJson('/api/v3/depth?symbol=USDCBRL');

    $response->assertOk()->assertExactJson([
        'lastUpdateId' => 1,
        'bids' => [
            ['5.09', '10'],
            ['5.08', '10'],
            ['5.07', '10'],
        ],
        'asks' => [
            ['5.11', '10'],
            ['5.12', '10'],
            ['5.13', '10'],
        ],
    ]);
});

it('has the level-1 bid/ask identical to the bookTicker — one price source, never two', function (): void {
    $depth = $this->getJson('/api/v3/depth?symbol=USDCBRL')->json();
    $book = $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL')->json();

    expect($depth['bids'][0][0])->toBe($book['bidPrice'])
        ->and($depth['asks'][0][0])->toBe($book['askPrice']);
});

it('trims the served levels to the requested limit, never amplifying past the config', function (): void {
    $trimmed = $this->getJson('/api/v3/depth?symbol=USDCBRL&limit=1');

    $trimmed->assertOk();
    expect($trimmed->json('bids'))->toHaveCount(1)
        ->and($trimmed->json('asks'))->toHaveCount(1);

    $amplified = $this->getJson('/api/v3/depth?symbol=USDCBRL&limit=500');

    expect($amplified->json('bids'))->toHaveCount(3);
});

it('refuses an unknown symbol with -1121', function (): void {
    $response = $this->getJson('/api/v3/depth?symbol=BTCBRL');

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});
