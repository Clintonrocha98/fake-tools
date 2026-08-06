<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
    config(['fake-binance-spot.symbols.USDCBRL.price' => '5.10', 'fake-binance-spot.symbols.USDCBRL.spread' => '0.02']);
});

it('lists the fill of an executed order with the same commission the POST reported', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $placed = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'trades-1',
    ]), [], $this->apiKeyHeader());
    $placed->assertOk();

    $response = $this->getJson(
        $this->signedUri('/api/v3/myTrades', ['symbol' => 'USDCBRL', 'orderId' => (string) $placed->json('orderId')]),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJsonCount(1)->assertJson([[
        'symbol' => 'USDCBRL',
        'id' => $placed->json('orderId'),
        'orderId' => $placed->json('orderId'),
        'orderListId' => -1,
        'price' => '5.11',
        'qty' => '10',
        'quoteQty' => '51.1',
        'commission' => $placed->json('fills.0.commission'),
        'commissionAsset' => $placed->json('fills.0.commissionAsset'),
        'isBuyer' => true,
        'isMaker' => false,
        'isBestMatch' => true,
    ]]);

    expect($response->json('0.time'))->toBeInt()->toBeGreaterThan(0);
});

it('filters by symbol, never leaking trades from another pair', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'trades-usdc',
    ]), [], $this->apiKeyHeader())->assertOk();

    $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDTBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'trades-usdt',
    ]), [], $this->apiKeyHeader())->assertOk();

    $response = $this->getJson(
        $this->signedUri('/api/v3/myTrades', ['symbol' => 'USDTBRL']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJsonCount(1)->assertJson([['symbol' => 'USDTBRL']]);
});

it('returns an empty list for a symbol with no executed orders', function (): void {
    $response = $this->getJson(
        $this->signedUri('/api/v3/myTrades', ['symbol' => 'USDCBRL']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson([]);
});

it('refuses a missing symbol with -1102', function (): void {
    $response = $this->getJson($this->signedUri('/api/v3/myTrades', []), $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_102]);
});

it('refuses an unknown symbol with -1121', function (): void {
    $response = $this->getJson(
        $this->signedUri('/api/v3/myTrades', ['symbol' => 'BTCBRL']),
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertJson(['code' => -1_121]);
});

it('refuses an unsigned request with the spot/wallet error envelope', function (): void {
    $response = $this->getJson('/api/v3/myTrades?symbol=USDCBRL');

    $response->assertStatus(401)->assertJson(['code' => -2_014]);
});
