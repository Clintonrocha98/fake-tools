<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;
use He4rt\Venue\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\Venue\Tests\Support\SignsRequests;

/*
 * Perna Spot: os quatro endpoints que `BinanceConversionGateway` bate — bookTicker,
 * exchangeInfo, account e order (POST + GET) — batidos exatamente como
 * `Brd\IntegrationBinance\Http\Requests\*` bate (mesmos params, assinatura de
 * verdade via {@see SignsRequests}), e comparados por FORMA contra os envelopes
 * gravados em `Fixtures.php`/`ConversionGatewayTest.php` do consumidor
 * ({@see AssertsRecordedShape}). Ver `fixtures/README.md` para a origem de cada
 * arquivo.
 */

uses(SignsRequests::class, AssertsRecordedShape::class);

beforeEach(function (): void {
    $this->configureVenueCredentials();
    config(['venue-spot.usdcbrl.price' => '5.10', 'venue-spot.usdcbrl.spread' => '0.02']);
});

it('answers GET /api/v3/ticker/bookTicker with the shape GetBookTickerRequest reads (public, unsigned)', function (): void {
    $response = $this->getJson('/api/v3/ticker/bookTicker?symbol=USDCBRL');

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('spot/book_ticker.json'),
        $response->json(),
    );
});

it('answers GET /api/v3/exchangeInfo with the shape ExchangeInfoResponse reads (public, unsigned)', function (): void {
    $response = $this->getJson('/api/v3/exchangeInfo?symbol=USDCBRL');

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('spot/exchange_info.json'),
        $response->json(),
    );
});

it('answers GET /api/v3/account with the shape AccountResponse reads, signed as GetAccountRequest signs', function (): void {
    (new CreditLedgerAccount)('BRL', '150');

    $response = $this->getJson($this->signedUri('/api/v3/account'), $this->apiKeyHeader());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('spot/account.json'),
        $response->json(),
    );
});

it('answers POST /api/v3/order with the shape SpotOrderResponse reads, signed as PlaceSpotOrderRequest signs', function (): void {
    (new CreditLedgerAccount)('BRL', '100000');

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '51.1',
        'newClientOrderId' => 'contract-place-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('spot/place_order_response.json'),
        $response->json(),
    );
});

it('answers GET /api/v3/order with the shape SpotOrderResponse reads on a re-read, signed as GetOrderRequest signs', function (): void {
    (new CreditLedgerAccount)('BRL', '100000');

    $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'contract-get-1',
    ]), [], $this->apiKeyHeader())->assertOk();

    $response = $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'contract-get-1']),
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('spot/get_order_response.json'),
        $response->json(),
    );
});

it('never emits a wire status outside the 8 values Brd\IntegrationBinance\Wire\BinanceOrderStatus::fromWire() accepts', function (): void {
    // O consumidor falha loud (MalformedBinanceResponse) num `status` fora deste
    // conjunto — vocabulário copiado de Wire/BinanceOrderStatus.php (ver fixtures/README.md).
    $consumerAcceptsWire = [
        'NEW', 'PARTIALLY_FILLED', 'FILLED', 'CANCELED', 'PENDING_CANCEL', 'REJECTED', 'EXPIRED', 'EXPIRED_IN_MATCH',
    ];

    $fakeEmitsWire = array_map(fn (OrderStatus $case): string => $case->value, OrderStatus::cases());

    expect($fakeEmitsWire)->toEqualCanonicalizing($consumerAcceptsWire);
});

it('serves a FILLED order status verbatim over the wire, one of the 8 accepted values', function (): void {
    (new CreditLedgerAccount)('BRL', '100000');

    $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL', 'side' => 'BUY', 'quoteOrderQty' => '51.1', 'newClientOrderId' => 'contract-status-filled',
    ]), [], $this->apiKeyHeader())->assertOk()->assertJson(['status' => 'FILLED']);
});

it('serves a REJECTED order status verbatim over the wire, one of the 8 accepted values', function (): void {
    SpotOrder::factory()->rejected()->create(['client_order_id' => 'contract-status-rejected']);

    $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'contract-status-rejected']),
        $this->apiKeyHeader(),
    )->assertOk()->assertJson(['status' => 'REJECTED']);
});

it('serves an EXPIRED order status verbatim over the wire, one of the 8 accepted values', function (): void {
    SpotOrder::factory()->partiallyFilledThenExpired()->create(['client_order_id' => 'contract-status-expired']);

    $this->getJson(
        $this->signedUri('/api/v3/order', ['symbol' => 'USDCBRL', 'origClientOrderId' => 'contract-status-expired']),
        $this->apiKeyHeader(),
    )->assertOk()->assertJson(['status' => 'EXPIRED']);
});
