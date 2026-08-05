<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Actions\DelayFiatBrcode;
use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureVenueCredentials());

it('sets the delay and resets the reads counter', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'brcode_reads_count' => 3,
    ]);

    $delayed = (new DelayFiatBrcode)->handle($order, 2);

    expect($delayed->brcode_delay_reads)->toBe(2)
        ->and($delayed->brcode_reads_count)->toBe(0);
});

it('clearing the delay (null) makes the brcode visible immediately', function (): void {
    $order = FiatOrder::factory()->create(['brcode_delay_reads' => 5]);

    $cleared = (new DelayFiatBrcode)->handle($order, reads: null);

    expect($cleared->brcodeVisible())->toBeTrue();
});

it('hides the pixcode for the first N reads, then reveals it', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);
    (new DelayFiatBrcode)->handle($order, 2);

    $uri = fn () => $this->signedUri('/sapi/v1/fiat/get-order-detail', ['orderNo' => $order->order_no]);

    // Leituras 1 e 2: ainda sem brcode.
    $this->getJson($uri(), $this->apiKeyHeader())->assertOk()->assertJson(['data' => ['pixcode' => null]]);
    $this->getJson($uri(), $this->apiKeyHeader())->assertOk()->assertJson(['data' => ['pixcode' => null]]);

    // Leitura 3 (> 2): brcode aparece.
    $this->getJson($uri(), $this->apiKeyHeader())->assertOk()->assertJson(['data' => ['pixcode' => $order->brcode]]);
});
