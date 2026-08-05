<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Spot\Models\SpotOrder;
use He4rt\Venue\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureVenueCredentials();
    config(['venue-spot.usdcbrl.price' => '5.10', 'venue-spot.usdcbrl.spread' => '0.02']);
});

it('retrieves an order by origClientOrderId with the same shape as the POST response, minus fills', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $this->postJson(
        $this->signedUri('/api/v3/order', [
            'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-get-1',
        ]),
        [],
        $this->apiKeyHeader(),
    )->assertOk();

    $response = $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'forex-get-1']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([
        'symbol' => 'USDCBRL',
        'clientOrderId' => 'forex-get-1',
        'status' => 'FILLED',
        'side' => 'BUY',
        'type' => 'MARKET',
        'executedQty' => '10',
        'cummulativeQuoteQty' => '51.1',
    ]);

    expect($response->json())->not->toHaveKey('fills');
});

it('returns the original order on a GET after a rejected duplicate POST', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');

    $this->postJson(
        $this->signedUri('/api/v3/order', [
            'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-get-dup',
        ]),
        [],
        $this->apiKeyHeader(),
    )->assertOk();

    $this->postJson(
        $this->signedUri('/api/v3/order', [
            'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'forex-get-dup',
        ]),
        [],
        $this->apiKeyHeader(),
    )->assertStatus(400);

    $response = $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'forex-get-dup']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson(['clientOrderId' => 'forex-get-dup', 'executedQty' => '10']);
});

it('refuses an unknown origClientOrderId with -2013', function (): void {
    $response = $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'does-not-exist']),
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertJson(['code' => -2_013]);
});

it('serves a REJECTED order produced by state on the DB, with no fill fields', function (): void {
    SpotOrder::factory()->rejected()->create(['client_order_id' => 'forex-rejected-1']);

    $response = $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'forex-rejected-1']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([
        'status' => 'REJECTED',
        'executedQty' => '0',
        'cummulativeQuoteQty' => '0',
    ]);
});

it('serves a partially filled then EXPIRED order produced by state on the DB', function (): void {
    SpotOrder::factory()->partiallyFilledThenExpired()->create(['client_order_id' => 'forex-partial-1']);

    $response = $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'forex-partial-1']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([
        'status' => 'EXPIRED',
        'executedQty' => '1.5',
    ]);
});
