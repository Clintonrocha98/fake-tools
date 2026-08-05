<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Actions\SetFiatOrderFrozen;
use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Tests\Support\SignsRequests;
use Illuminate\Support\Facades\Date;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureVenueCredentials());

it('toggles the frozen flag', function (): void {
    $order = FiatOrder::factory()->create();

    $frozen = (new SetFiatOrderFrozen)->handle($order, frozen: true);
    expect($frozen->frozen)->toBeTrue();

    $unfrozen = (new SetFiatOrderFrozen)->handle($frozen, frozen: false);
    expect($unfrozen->frozen)->toBeFalse();
});

it('a frozen order never advances via the lazy clock, even past the advance window', function (): void {
    config(['venue-fiat.advance_seconds' => 60]);

    Date::setTestNow(now()->subSeconds(120));
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);
    (new SetFiatOrderFrozen)->handle($order, frozen: true);
    Date::setTestNow();

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_PROCESSING']]);

    expect($order->refresh()->status)->toBe(FiatOrderStatus::Processing);
});

it('unfreezing resumes the lazy advance', function (): void {
    config(['venue-fiat.advance_seconds' => 60]);

    Date::setTestNow(now()->subSeconds(120));
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);
    (new SetFiatOrderFrozen)->handle($order, frozen: true);
    Date::setTestNow();

    (new SetFiatOrderFrozen)->handle($order->refresh(), frozen: false);

    $this->getJson($this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]), $this->apiKeyHeader())
        ->assertOk()
        ->assertJson(['data' => ['status' => 'ORDER_SUCCESS']]);
});
