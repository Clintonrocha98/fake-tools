<?php

declare(strict_types=1);

it('returns symbols[] with the base/quote precisions and the LOT_SIZE/NOTIONAL filters, public and unsigned', function (): void {
    $response = $this->getJson('/api/v3/exchangeInfo?symbol=USDCBRL');

    $response->assertOk()->assertJson([
        'symbols' => [[
            'symbol' => 'USDCBRL',
            'baseAsset' => 'USDC',
            'quoteAsset' => 'BRL',
            'baseAssetPrecision' => 8,
            'quoteAssetPrecision' => 8,
            'filters' => [
                ['filterType' => 'LOT_SIZE', 'minQty' => '0.00000001', 'maxQty' => '9000000.00000000', 'stepSize' => '0.00000001'],
                ['filterType' => 'NOTIONAL', 'minNotional' => '10', 'applyToMarket' => true],
            ],
        ]],
    ]);
});

it('refuses an unknown symbol with -1121', function (): void {
    $response = $this->getJson('/api/v3/exchangeInfo?symbol=BTCBRL');

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});

it('refuses a missing symbol with -1121', function (): void {
    $response = $this->getJson('/api/v3/exchangeInfo');

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});
