<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureFakeBinanceCredentials());

it('opens a fiat deposit order and answers the documented success envelope', function (): void {
    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '4924.50'],
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([
        'code' => '000000',
        'message' => 'success',
    ]);

    $orderId = $response->json('data.orderId');

    expect($orderId)->toBeString()->not->toBeEmpty();

    $order = FiatOrder::query()->where('order_no', $orderId)->firstOrFail();

    expect($order->currency)->toBe('BRL')
        ->and($order->payment_method)->toBe('Pix')
        ->and((string) $order->amount)->toBe('4924.500000000000000000')
        ->and($order->status->value)->toBe('processing')
        ->and($order->brcode)->not->toBeNull()
        ->and(str_starts_with((string) $order->brcode, '000201'))->toBeTrue();
});

it('creates a distinct order id for every deposit', function (): void {
    $body = ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '100'];

    $first = $this->postJson($this->signedUri('/sapi/v1/fiat/deposit'), $body, $this->apiKeyHeader());
    $second = $this->postJson($this->signedUri('/sapi/v1/fiat/deposit'), $body, $this->apiKeyHeader());

    expect($first->json('data.orderId'))->not->toBe($second->json('data.orderId'));
});

it('refuses with 100001 when the fiat service is disabled by config, HTTP 200', function (): void {
    config(['fake-binance-fiat.deposit_enabled' => false]);

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '100'],
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson([
        'code' => '100001',
        'message' => 'fiat service not enabled',
        'success' => false,
        'data' => null,
    ]);

    expect(FiatOrder::query()->count())->toBe(0);
});

it('refuses an unsupported currency with -16010, HTTP 200', function (): void {
    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'USD', 'apiPaymentMethod' => 'Pix', 'amount' => '100'],
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([
        'code' => '-16010',
        'success' => false,
        'data' => null,
    ]);
});

it('refuses an unsupported payment method with -16010, HTTP 200', function (): void {
    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Boleto', 'amount' => '100'],
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson(['code' => '-16010']);
});

it('refuses an amount above the configured deposit limit with -16007, HTTP 200', function (): void {
    config(['fake-binance-fiat.deposit_limit' => '1000']);

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '1000.01'],
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson(['code' => '-16007']);
});

it('accepts an amount at exactly the configured deposit limit', function (): void {
    config(['fake-binance-fiat.deposit_limit' => '1000']);

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/fiat/deposit'),
        ['currency' => 'BRL', 'apiPaymentMethod' => 'Pix', 'amount' => '1000'],
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson(['code' => '000000']);
});

it('validates the required body fields, answering the fiat -1102 envelope', function (): void {
    $response = $this->postJson($this->signedUri('/sapi/v1/fiat/deposit'), [], $this->apiKeyHeader());

    $response->assertStatus(400)->assertExactJson([
        'code' => '-1102',
        'message' => 'A mandatory parameter was not sent, was empty/null, or malformed.',
        'success' => false,
        'data' => null,
    ]);
});
