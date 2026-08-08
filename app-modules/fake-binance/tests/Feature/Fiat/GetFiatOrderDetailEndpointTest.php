<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Tests\Support\SignsRequests;
use Illuminate\Support\Facades\Date;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureFakeBinanceCredentials());

it('answers ORDER_PROCESSING with the immediate brcode, live dialect by default', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'code' => '000000',
        'message' => 'success',
        'data' => [
            'orderNo' => $order->order_no,
            'status' => 'ORDER_PROCESSING',
            'orderStatus' => 'ORDER_PROCESSING',
        ],
    ]);

    expect($response->json('data.pixcode'))->toBe($order->brcode)
        ->and(str_starts_with((string) $response->json('data.pixcode'), '000201'))->toBeTrue();
});

it('answers the documented order data shape, fiat scale kept at two decimals', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'currency' => 'BRL',
        'payment_method' => 'Pix',
        'amount' => '4924.50',
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'data' => [
            'fiatCurrency' => 'BRL',
            'currency' => 'BRL',
            'amount' => '4924.50',
            'method' => 'Pix',
            'totalFee' => '0.00',
        ],
    ]);

    expect($response->json('data.createTime'))->toBeInt()
        ->and($response->json('data.updateTime'))->toBeInt()
        ->and($response->json('data.pixcode'))->toBe($order->brcode);
});

it('serves the documented field names alongside the observed ones — orderId, fee and the error pair', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'data' => [
            'orderNo' => $order->order_no,
            'orderId' => $order->order_no,
            'fee' => '0.00',
            'totalFee' => '0.00',
        ],
    ]);

    // Um happy path nunca carrega motivo de falha, mas as keys existem sempre:
    // a doc as lista, e um consumidor que faça `array_key_exists` não pode
    // descobrir na venue real que o fake as omitia.
    expect($response->json('data'))->toHaveKeys(['errorCode', 'errorMessage'])
        ->and($response->json('data.errorCode'))->toBeNull()
        ->and($response->json('data.errorMessage'))->toBeNull();
});

it('carries the failure reason in errorCode/errorMessage once a forced status kills the order', function (FiatOrderStatus $status, string $errorCode): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing, 'forced_status' => $status]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson(['data' => ['errorCode' => $errorCode]]);

    expect($response->json('data.errorMessage'))->toBeString()->not->toBeEmpty();
})->with([
    'failed' => [FiatOrderStatus::Failed, 'PAYMENT_FAILED'],
    'expired' => [FiatOrderStatus::Expired, 'ORDER_EXPIRED'],
    'cancelled' => [FiatOrderStatus::Cancelled, 'ORDER_CANCELLED'],
    'refunding' => [FiatOrderStatus::Refunding, 'REFUND_IN_PROGRESS'],
    'refunded' => [FiatOrderStatus::Refunded, 'PAYMENT_REFUNDED'],
    'refund failed' => [FiatOrderStatus::RefundFailed, 'REFUND_FAILED'],
    'partial credit stopped' => [FiatOrderStatus::PartialCreditStopped, 'PARTIAL_CREDIT_STOPPED'],
]);

it('leaves the error pair null for an unmodeled wire status — the fake never invents a reason it does not know', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_wire_status' => 'Some Future Status',
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk();

    expect($response->json('data.errorCode'))->toBeNull()
        ->and($response->json('data.errorMessage'))->toBeNull();
});

it('serves the ext object with the brcode on the root by default', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk();

    expect($response->json('data.ext'))->toBeArray()
        ->and($response->json('data.ext'))->not->toHaveKey('pixCode')
        ->and($response->json('data.pixcode'))->toBe($order->brcode);
});

it('nests the brcode inside ext and drops it from the root when the placement scenario is armed', function (): void {
    config(['fake-binance-fiat.brcode_placement' => 'ext']);

    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk();

    expect($response->json('data'))->not->toHaveKey('pixcode')
        ->and($response->json('data.ext.pixCode'))->toBe($order->brcode);
});

it('drops the pixcode once a forced order dies in a terminal failure state', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Failed,
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson(['data' => ['pixcode' => null]]);
});

it('answers the classic dialect when configured', function (): void {
    config(['fake-binance-fiat.status_dialect' => 'classic']);

    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'Processing', 'orderStatus' => 'Processing']]);
});

it('lazily advances a processing order to ORDER_SUCCESS once it is older than the advance window, crediting the ledger', function (): void {
    config(['fake-binance-fiat.advance_seconds' => 60]);

    Date::setTestNow(now()->subSeconds(61));
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing, 'currency' => 'BRL', 'amount' => '500']);
    Date::setTestNow();

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson(['data' => ['status' => 'ORDER_SUCCESS', 'orderStatus' => 'ORDER_SUCCESS']]);

    $order->refresh();

    expect($order->status)->toBe(FiatOrderStatus::Success)
        ->and($order->credited_at)->not->toBeNull();

    $account = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    expect((string) $account->free)->toBe('500.000000000000000000');
});

it('does not advance a processing order still inside the advance window', function (): void {
    config(['fake-binance-fiat.advance_seconds' => 60]);

    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_PROCESSING']]);

    expect($order->refresh()->status)->toBe(FiatOrderStatus::Processing);
});

it('lazily advances via a string config value, the shape a real .env produces', function (): void {
    config(['fake-binance-fiat.advance_seconds' => '60']);

    Date::setTestNow(now()->subSeconds(61));
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);
    Date::setTestNow();

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_SUCCESS']]);
});

it('credits the ledger only once across repeated reads of an already-successful order', function (): void {
    config(['fake-binance-fiat.advance_seconds' => 60]);

    Date::setTestNow(now()->subSeconds(61));
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing, 'currency' => 'BRL', 'amount' => '500']);
    Date::setTestNow();

    $uri = $this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]);

    $this->getJson($uri, $this->apiKeyHeader())->assertOk();
    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())->assertOk();

    $account = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    expect((string) $account->free)->toBe('500.000000000000000000');
});

it('honors forced_status as an override that wins over the lazy advance, without crediting a forced failure', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Failed,
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson(['data' => ['status' => 'ORDER_FAILED', 'orderStatus' => 'ORDER_FAILED']]);

    expect($order->refresh()->credited_at)->toBeNull()
        ->and(LedgerAccount::query()->where('asset', 'BRL')->exists())->toBeFalse();
});

it('produces every forced failure status on command via forced_status', function (FiatOrderStatus $status, string $wire): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing, 'forced_status' => $status]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => $wire]]);
})->with([
    'need additional action' => [FiatOrderStatus::NeedAdditionalAction, 'ORDER_NEED_ADDITIONAL_ACTION'],
    'failed' => [FiatOrderStatus::Failed, 'ORDER_FAILED'],
    'expired' => [FiatOrderStatus::Expired, 'ORDER_EXPIRED'],
    'cancelled' => [FiatOrderStatus::Cancelled, 'ORDER_CANCELLED'],
    'refunding' => [FiatOrderStatus::Refunding, 'ORDER_REFUNDING'],
    'refunded' => [FiatOrderStatus::Refunded, 'ORDER_REFUNDED'],
    'refund failed' => [FiatOrderStatus::RefundFailed, 'ORDER_REFUND_FAILED'],
    'partial credit stopped' => [FiatOrderStatus::PartialCreditStopped, 'ORDER_PARTIAL_CREDIT_STOPPED'],
]);

it('resumes the lazy advance after a forced status is cleared', function (): void {
    config(['fake-binance-fiat.advance_seconds' => 60]);

    Date::setTestNow(now()->subSeconds(61));
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::NeedAdditionalAction,
    ]);
    Date::setTestNow();

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_NEED_ADDITIONAL_ACTION']]);

    // `status` continua Processing por baixo — só a leitura foi mascarada.
    expect($order->refresh()->status)->toBe(FiatOrderStatus::Processing);

    $order->update(['forced_status' => null]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_SUCCESS']]);

    expect($order->refresh()->status)->toBe(FiatOrderStatus::Success)
        ->and($order->credited_at)->not->toBeNull();
});

it('echoes an unmodeled wire status verbatim and never credits', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_wire_status' => 'Some Future Status',
        'currency' => 'BRL',
        'amount' => '500',
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'data' => ['status' => 'Some Future Status', 'orderStatus' => 'Some Future Status'],
    ]);

    expect($order->refresh()->status)->toBe(FiatOrderStatus::Processing)
        ->and($order->credited_at)->toBeNull()
        ->and(LedgerAccount::query()->where('asset', 'BRL')->exists())->toBeFalse();
});

it('credits the ledger once when a forced override sets Success directly', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Success,
        'currency' => 'BRL',
        'amount' => '250',
    ]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())->assertOk();
    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())->assertOk();

    $account = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    expect((string) $account->free)->toBe('250.000000000000000000');
});

it('refuses an unknown orderNo with -16011, HTTP 200', function (): void {
    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => 'does-not-exist']), $this->apiKeyHeader());

    $response->assertOk()->assertExactJson([
        'code' => '-16011',
        'message' => 'fiat order not found: does-not-exist',
        'success' => false,
        'data' => null,
    ]);
});

it('validates that orderNo is required, answering the fiat -1102 envelope', function (): void {
    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail'), $this->apiKeyHeader())
        ->assertStatus(400)
        ->assertExactJson([
            'code' => '-1102',
            'message' => 'A mandatory parameter was not sent, was empty/null, or malformed.',
            'success' => false,
            'data' => null,
        ]);
});
