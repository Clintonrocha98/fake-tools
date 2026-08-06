<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Enums\FiatStatusDialect;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeBinance\Tests\Support\EmvDecoder;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

/*
 * Perna Fiat: POST /sapi/v1/fiat/deposit e GET /sapi/v1/fiat/get-order-detail,
 * batidos como `CreateFiatDepositRequest`/`GetFiatOrderDetailRequest` batem (body
 * JSON fora da assinatura, orderNo na query), e comparados por FORMA contra os
 * envelopes gravados em `VenueFundingGatewayTest.php` do consumidor. Cobre também
 * os dois dialetos de status (`live`/`classic`) e a extração de brcode por
 * key-normalização — a mesma que `BinanceVenueFundingGateway::extractBrcode()` faz.
 */

uses(SignsRequests::class, AssertsRecordedShape::class);

beforeEach(fn () => $this->configureFakeBinanceCredentials());

it('answers POST /sapi/v1/fiat/deposit with the success envelope FiatDepositResponse reads', function (): void {
    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '4924.50'],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    // `code` não é um campo qualquer: `FiatDepositResponse::successful()` exige
    // exatamente `'000000'` — o tipo-só bastaria para `'0'` passar em silêncio.
    $expected = $this->loadContractFixture('fiat/create_deposit_success.json');
    $expected['code'] = $this->exactValue('000000');

    $this->assertMatchesRecordedShape($expected, $response->json());
});

it('answers POST /sapi/v1/fiat/deposit with the refusal envelope FiatDepositResponse reads, HTTP 200', function (): void {
    config(['fake-binance-fiat.deposit_enabled' => false]);

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '100'],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    // Simétrico ao teste de sucesso: `code` de recusa nunca pode acidentalmente
    // virar `'000000'` — isso faria `successful()` do consumidor tratar uma
    // recusa como crédito.
    $expected = $this->loadContractFixture('fiat/create_deposit_refused.json');
    expect($expected['code'])->not->toBe('000000');
    $expected['code'] = $this->exactValue($expected['code']);

    $this->assertMatchesRecordedShape($expected, $response->json());
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

    config(['fake-binance-fiat.status_dialect' => 'classic']);

    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing, 'forced_status' => $status !== FiatOrderStatus::Processing ? $status : null]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => $classicWire]]);
})->with([
    // (live wire de referência, classic wire, status) — os pares exatos que
    // `VenueFundingGatewayTest::it('re-reads one order and translates the closed
    // status vocabulary to the neutral buckets')` exercita.
    'Successful credits' => ['ORDER_SUCCESS', 'Successful', FiatOrderStatus::Success],
    'Finished credits' => ['ORDER_COMPLETED', 'Finished', FiatOrderStatus::Completed],
    'Processing stays pending' => ['ORDER_PROCESSING', 'Processing', FiatOrderStatus::Processing],
    'Failed dies' => ['ORDER_FAILED', 'Failed', FiatOrderStatus::Failed],
    'Expired dies' => ['ORDER_EXPIRED', 'Expired', FiatOrderStatus::Expired],
    'Refunded dies' => ['ORDER_REFUNDED', 'Refunded', FiatOrderStatus::Refunded],
]);

it("echoes an unmodeled wire status verbatim in both dialects, the exact fixture the consumer's fail-closed arm exercises", function (string $dialect): void {
    // `forced_wire_status` nunca passa por `FiatOrderStatus::toWire()` — é o
    // vocabulário fora do enum que o dataset `VenueFundingGatewayTest::it('re-reads
    // one order and translates the closed status vocabulary to the neutral
    // buckets')` chama de 'unknown vocabulary stays pending (fail-closed)'.
    config(['fake-binance-fiat.status_dialect' => $dialect]);

    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_wire_status' => 'Some Future Status',
    ]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'Some Future Status', 'orderStatus' => 'Some Future Status']]);
})->with(['live', 'classic']);

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
    'Finished credits (live)' => [FiatOrderStatus::Completed, 'ORDER_COMPLETED'],
    'Processing stays pending (live)' => [FiatOrderStatus::Processing, 'ORDER_PROCESSING'],
    'Failed dies (live)' => [FiatOrderStatus::Failed, 'ORDER_FAILED'],
    'need additional action surfaces distinctly (live)' => [FiatOrderStatus::NeedAdditionalAction, 'ORDER_NEED_ADDITIONAL_ACTION'],
]);

it('never emits a Live-dialect wire value outside Brd\IntegrationBinance\Funding\BinanceFiatOrderStatus, except the two tracked gaps', function (): void {
    // Vocabulário copiado verbatim de BinanceFiatOrderStatus (ver fixtures/README.md).
    $consumerAccepts = [
        'processing', 'successful', 'finished', 'failed', 'expired', 'refunding', 'refunded',
        'refund failed', 'order partial credit stopped',
        'order_processing', 'order_need_additional_action', 'order_success', 'order_completed',
        'order_failed', 'order_expired', 'order_cancelled', 'order_refunding', 'order_refunded',
    ];

    // `toFundingState()` normaliza só com `mb_strtolower`+`mb_trim` (sem trocar
    // `_` por espaço) antes do `tryFrom()` — a mesma normalização é reproduzida
    // aqui para provar o cruzamento real, não uma comparação case-insensitive
    // qualquer.
    $normalize = static fn (string $wire): string => mb_strtolower(mb_trim($wire));

    // GAP RASTREADO: no dialeto Live, o fake responde
    // `ORDER_REFUND_FAILED`/`ORDER_PARTIAL_CREDIT_STOPPED`, mas
    // `BinanceFiatOrderStatus` não tem par `order_*` para nenhum dos dois — o
    // `tryFrom()` do consumidor devolve null e o `match` fail-closed prende a
    // ordem em `Pending` para sempre, mesmo sendo um estado TERMINAL de falha.
    // Ver ADR-0001 (fake-binance) para o registro da decisão e o link do tracking.
    $trackedGaps = ['order_refund_failed', 'order_partial_credit_stopped'];

    foreach (FiatOrderStatus::cases() as $status) {
        foreach (FiatStatusDialect::cases() as $dialect) {
            $normalized = $normalize($status->toWire($dialect));

            if (in_array($normalized, $trackedGaps, strict: true)) {
                expect($consumerAccepts)->not->toContain($normalized);

                continue;
            }

            expect($consumerAccepts)->toContain($normalized);
        }
    }
});

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

it('serves a pixcode the fake-starkbank preview can decode — the cross-fake contract travels in the payload, never over the wire', function (): void {
    // Os dois fakes nunca se consultam: o preview do fake-starkbank recebe este
    // mesmo texto e resolve a chave PIX do campo 26 no seu registro DICT. Se o
    // TLV não fechar aqui, a perna de funding do consumidor morre lá.
    config(['fake-binance-fiat.pix_key' => 'funding@fake-binance.dev']);

    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'amount' => '4924.50',
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $pixcode = (string) $response->json('data.pixcode');

    expect(EmvDecoder::crcIsValid($pixcode))->toBeTrue()
        ->and(EmvDecoder::value($pixcode, '54'))->toBe('4924.50')
        ->and(EmvDecoder::value(EmvDecoder::value($pixcode, '26'), '01'))->toBe('funding@fake-binance.dev');
});

it('drops the brcode once the order dies in a terminal failure state, so extractBrcode() finds nothing', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Failed,
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    expect($response->json('data.pixcode'))->toBeNull();
});
