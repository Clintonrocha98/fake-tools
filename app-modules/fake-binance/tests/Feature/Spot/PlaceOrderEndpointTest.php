<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
    config(['fake-binance-spot.symbols.USDCBRL.price' => '5.10', 'fake-binance-spot.symbols.USDCBRL.spread' => '0.02']);
});

it('fills a BUY MARKET order at the ask price, spending quoteOrderQty and crediting the ledger net of commission', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '51.1',
        'newClientOrderId' => 'forex-buy-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'symbol' => 'USDCBRL',
        'clientOrderId' => 'forex-buy-1',
        'status' => 'FILLED',
        'side' => 'BUY',
        'type' => 'MARKET',
        'executedQty' => '10',
        'cummulativeQuoteQty' => '51.1',
        'fills' => [
            ['price' => '5.11', 'qty' => '10', 'commission' => '0.01', 'commissionAsset' => 'USDC'],
        ],
    ]);
    $response->assertJsonStructure(['orderId']);
    expect($response->json('orderId'))->toBeInt();

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($brl->free)->toBe('99948.900000000000000000')
        ->and($usdc->free)->toBe('9.990000000000000000');
});

it('fills a SELL MARKET order at the bid price, selling quantity and crediting the ledger net of commission', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '1000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'SELL',
        'type' => 'MARKET',
        'quantity' => '10',
        'newClientOrderId' => 'forex-sell-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'status' => 'FILLED',
        'side' => 'SELL',
        'executedQty' => '10',
        'cummulativeQuoteQty' => '50.9',
        'fills' => [
            ['price' => '5.09', 'qty' => '10', 'commission' => '0.0509', 'commissionAsset' => 'BRL'],
        ],
    ]);

    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();
    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($usdc->free)->toBe('990.000000000000000000')
        ->and($brl->free)->toBe('50.849100000000000000');
});

it('fills a SELL MARKET order denominated in the quote asset, selling quoteOrderQty / bid of the base', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '1000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'SELL',
        'type' => 'MARKET',
        'quoteOrderQty' => '50.9',
        'newClientOrderId' => 'forex-sell-quote-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'status' => 'FILLED',
        'side' => 'SELL',
        'executedQty' => '10',
        'cummulativeQuoteQty' => '50.9',
        'fills' => [
            ['price' => '5.09', 'qty' => '10', 'commission' => '0.0509', 'commissionAsset' => 'BRL'],
        ],
    ]);

    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();
    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($usdc->free)->toBe('990.000000000000000000')
        ->and($brl->free)->toBe('50.849100000000000000');
});

it('fills a BUY MARKET order denominated in the base asset, spending quantity * ask of the quote', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quantity' => '10',
        'newClientOrderId' => 'forex-buy-base-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'status' => 'FILLED',
        'side' => 'BUY',
        'executedQty' => '10',
        'cummulativeQuoteQty' => '51.1',
        'fills' => [
            ['price' => '5.11', 'qty' => '10', 'commission' => '0.01', 'commissionAsset' => 'USDC'],
        ],
    ]);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($brl->free)->toBe('99948.900000000000000000')
        ->and($usdc->free)->toBe('9.990000000000000000');
});

it('fills a BUY MARKET order on USDTBRL, debiting BRL and crediting USDT net of commission', function (): void {
    config(['fake-binance-spot.symbols.USDTBRL.price' => '5.10', 'fake-binance-spot.symbols.USDTBRL.spread' => '0.02']);
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDTBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '51.1',
        'newClientOrderId' => 'forex-usdt-buy-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'symbol' => 'USDTBRL',
        'status' => 'FILLED',
        'side' => 'BUY',
        'executedQty' => '10',
        'cummulativeQuoteQty' => '51.1',
        'fills' => [
            ['price' => '5.11', 'qty' => '10', 'commission' => '0.01', 'commissionAsset' => 'USDT'],
        ],
    ]);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    $usdt = LedgerAccount::query()->where('asset', 'USDT')->firstOrFail();

    expect($brl->free)->toBe('99948.900000000000000000')
        ->and($usdt->free)->toBe('9.990000000000000000');
});

it('refuses quantity and quoteOrderQty together with -1102', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'quantity' => '10',
        'quoteOrderQty' => '51.1',
        'newClientOrderId' => 'forex-both-params-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_102]);
    expect(SpotOrder::query()->where('client_order_id', 'forex-both-params-1')->exists())->toBeFalse();
});

it('refuses a duplicate newClientOrderId with -2010 without re-executing the swap', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $params = [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-dup-1',
    ];

    $this->postJson($this->signedUri('/api/v3/order', $params), [], $this->apiKeyHeader())->assertOk();

    $second = $this->postJson($this->signedUri('/api/v3/order', $params), [], $this->apiKeyHeader());

    $second->assertStatus(400)->assertJson(['code' => -2_010]);

    expect(SpotOrder::query()->where('client_order_id', 'forex-dup-1')->count())->toBe(1);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    expect($brl->free)->toBe('99948.900000000000000000');
});

it('refuses a MARKET BUY with -2010 when the BRL balance cannot cover the spend', function (): void {
    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-insufficient-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -2_010]);

    expect(SpotOrder::query()->where('client_order_id', 'forex-insufficient-1')->exists())->toBeFalse()
        ->and(LedgerAccount::query()->where('asset', 'USDC')->exists())->toBeFalse();
});

it('refuses a MARKET order missing the mandatory parameters with -1102', function (): void {
    $response = $this->postJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'side' => 'BUY']),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertJson(['code' => -1_102]);
});

it('refuses an unsigned POST /api/v3/order with the spot/wallet error envelope', function (): void {
    $response = $this->postJson('/api/v3/order', ['symbol' => 'USDCBRL', 'side' => 'BUY']);

    $response->assertStatus(401)->assertJson(['code' => -2_014]);
});

it('refuses an unknown symbol with -1121', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'BTCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-unknown-symbol-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
    expect(SpotOrder::query()->where('client_order_id', 'forex-unknown-symbol-1')->exists())->toBeFalse();
});

it('refuses a type other than MARKET with -1116', function (): void {
    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'type' => 'LIMIT', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-limit-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_116]);
});

it('refuses an invalid side with -1117', function (): void {
    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'HOLD', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-side-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_117]);
});

it('debits BRL byte-for-byte equal to the cummulativeQuoteQty reported on the wire, even when the division truncates', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '20');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '15', 'newClientOrderId' => 'forex-truncation-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'executedQty' => '2.93542074',
        'cummulativeQuoteQty' => '14.9999999814',
    ]);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($brl->free)->toBe('5.000000018600000000');
});

it('refuses a SELL below minQty with a filter failure', function (): void {
    config(['fake-binance-spot.symbols.USDCBRL.filters.min_qty' => '5']);
    (new CreditLedgerAccount)->handle('USDC', '1000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'SELL', 'quantity' => '1', 'newClientOrderId' => 'forex-lot-size-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_013]);
    expect(SpotOrder::query()->where('client_order_id', 'forex-lot-size-1')->exists())->toBeFalse();
});

it('refuses a BUY below minNotional with a filter failure', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '1', 'newClientOrderId' => 'forex-notional-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_013]);
    expect(SpotOrder::query()->where('client_order_id', 'forex-notional-1')->exists())->toBeFalse();
});

it('gates a BUY denominated in the base by minQty — the parameter, not the side, picks the filter', function (): void {
    config(['fake-binance-spot.symbols.USDCBRL.filters.min_qty' => '5']);
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quantity' => '1', 'newClientOrderId' => 'forex-buy-lot-size-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_013]);
    expect(SpotOrder::query()->where('client_order_id', 'forex-buy-lot-size-1')->exists())->toBeFalse();
});

it('gates a SELL denominated in the quote by minNotional — the parameter, not the side, picks the filter', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '1000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'SELL', 'quoteOrderQty' => '1', 'newClientOrderId' => 'forex-sell-notional-1',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_013]);
    expect(SpotOrder::query()->where('client_order_id', 'forex-sell-notional-1')->exists())->toBeFalse();
});
