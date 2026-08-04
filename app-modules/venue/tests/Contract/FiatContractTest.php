<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Enums\FiatStatusDialect;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\Venue\Tests\Support\SignsRequests;

/*
 * Perna Fiat: POST /sapi/v1/fiat/deposit e GET /sapi/v1/fiat/get-order-detail,
 * batidos como `CreateFiatDepositRequest`/`GetFiatOrderDetailRequest` batem (body
 * JSON fora da assinatura, orderNo na query), e comparados por FORMA contra os
 * envelopes gravados em `VenueFundingGatewayTest.php` do consumidor. Cobre também
 * os dois dialetos de status (`live`/`classic`) e a extração de brcode por
 * key-normalização — a mesma que `BinanceVenueFundingGateway::extractBrcode()` faz.
 */

uses(SignsRequests::class, AssertsRecordedShape::class);

beforeEach(fn () => $this->configureVenueCredentials());

it('answers POST /sapi/v1/fiat/deposit with the success envelope FiatDepositResponse reads', function (): void {
    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '4924.50'],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('fiat/create_deposit_success.json'),
        $response->json(),
    );
});

it('answers POST /sapi/v1/fiat/deposit with the refusal envelope FiatDepositResponse reads, HTTP 200', function (): void {
    config(['venue-fiat.deposit_enabled' => false]);

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '100'],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('fiat/create_deposit_refused.json'),
        $response->json(),
    );
});

it('answers GET /sapi/v1/fiat/get-order-detail with the envelope FiatOrderDetailResponse reads, live dialect', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('fiat/get_order_detail_success.json'),
        $response->json(),
    );
});

it("speaks the classic dialect vocabulary the consumer's FundingGateway test names verbatim", function (string $liveWire, string $classicWire, FiatOrderStatus $status): void {
    // A dupla live/classic precisa bater com `FiatOrderStatus::toWire()` para as
    // duas — provando que o par do dataset não é um valor solto, digitado à mão.
    expect($status->toWire(FiatStatusDialect::Live))->toBe($liveWire)
        ->and($status->toWire(FiatStatusDialect::Classic))->toBe($classicWire);

    config(['venue-fiat.status_dialect' => 'classic']);

    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing, 'forced_status' => $status !== FiatOrderStatus::Processing ? $status : null]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => $classicWire]]);
})->with([
    // (live wire de referência, classic wire, status) — os pares exatos que
    // `VenueFundingGatewayTest::it('re-reads one order and translates the closed
    // status vocabulary to the neutral buckets')` exercita.
    'Successful credits' => ['ORDER_SUCCESS', 'Successful', FiatOrderStatus::Success],
    'Processing stays pending' => ['ORDER_PROCESSING', 'Processing', FiatOrderStatus::Processing],
    'Failed dies' => ['ORDER_FAILED', 'Failed', FiatOrderStatus::Failed],
    'Expired dies' => ['ORDER_EXPIRED', 'Expired', FiatOrderStatus::Expired],
    'Refunded dies' => ['ORDER_REFUNDED', 'Refunded', FiatOrderStatus::Refunded],
]);

it('speaks the live (SCREAMING_SNAKE) dialect vocabulary by default, including ORDER_NEED_ADDITIONAL_ACTION', function (FiatOrderStatus $status, string $liveWire): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => $status !== FiatOrderStatus::Processing ? $status : null,
    ]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => $liveWire, 'orderStatus' => $liveWire]]);
})->with([
    'Successful credits (live)' => [FiatOrderStatus::Success, 'ORDER_SUCCESS'],
    'Processing stays pending (live)' => [FiatOrderStatus::Processing, 'ORDER_PROCESSING'],
    'Failed dies (live)' => [FiatOrderStatus::Failed, 'ORDER_FAILED'],
    'need additional action surfaces distinctly (live)' => [FiatOrderStatus::NeedAdditionalAction, 'ORDER_NEED_ADDITIONAL_ACTION'],
]);

it('extracts the brcode via the same key-normalization walk BinanceVenueFundingGateway::extractBrcode() does', function (): void {
    // A extração do consumidor normaliza a key (minúsculo, sem `_`/`-`) antes de
    // comparar — `pixcode` (o que o fake emite) bate com `pixCode`/`pix_code`
    // sem que nenhum dos dois precise se alinhar ao outro literalmente.
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $data = (array) $response->json('data');

    $normalizedKeys = array_map(
        static fn (string $key): string => str_replace(['_', '-'], '', mb_strtolower($key)),
        array_keys($data),
    );

    expect($normalizedKeys)->toContain('pixcode')
        ->and($response->json('data.pixcode'))->toBe($order->brcode)
        ->and(str_starts_with((string) $response->json('data.pixcode'), '000201'))->toBeTrue();
});

it('drops the brcode once the order dies in a terminal failure state, so extractBrcode() finds nothing', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Failed,
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    expect($response->json('data.pixcode'))->toBeNull();
});
