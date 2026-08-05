<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Ledger\Models\LedgerAccount;
use He4rt\Venue\Tests\Support\SignsRequests;
use Illuminate\Support\Facades\Date;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureVenueCredentials());

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

it('drops the pixcode once a forced order dies in a terminal failure state', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Failed,
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader());

    $response->assertOk()->assertJson(['data' => ['pixcode' => null]]);
});

it('answers the classic dialect when configured', function (): void {
    config(['venue-fiat.status_dialect' => 'classic']);

    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'Processing', 'orderStatus' => 'Processing']]);
});

it('lazily advances a processing order to ORDER_SUCCESS once it is older than the advance window, crediting the ledger', function (): void {
    config(['venue-fiat.advance_seconds' => 60]);

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
    config(['venue-fiat.advance_seconds' => 60]);

    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_PROCESSING']]);

    expect($order->refresh()->status)->toBe(FiatOrderStatus::Processing);
});

it('lazily advances via a string config value, the shape a real .env produces', function (): void {
    config(['venue-fiat.advance_seconds' => '60']);

    Date::setTestNow(now()->subSeconds(61));
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);
    Date::setTestNow();

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_SUCCESS']]);
});

it('credits the ledger only once across repeated reads of an already-successful order', function (): void {
    config(['venue-fiat.advance_seconds' => 60]);

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
    config(['venue-fiat.advance_seconds' => 60]);

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
