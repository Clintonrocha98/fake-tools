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
]);

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

it('validates that orderNo is required', function (): void {
    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail'), $this->apiKeyHeader())
        ->assertStatus(422);
});
