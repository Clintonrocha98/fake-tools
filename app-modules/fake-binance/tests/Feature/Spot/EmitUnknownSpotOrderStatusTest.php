<?php

declare(strict_types=1);

use He4rt\FakeBinance\Spot\Actions\EmitUnknownSpotOrderStatus;
use He4rt\FakeBinance\Spot\DTOs\SpotOrderView;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Models\SpotOrder;

it('sets an arbitrary status string, without touching the real status column', function (): void {
    $order = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);

    $emitted = (new EmitUnknownSpotOrderStatus)->handle($order, 'SOME_FUTURE_STATE');

    expect($emitted->raw_status_override)->toBe('SOME_FUTURE_STATE')
        ->and($emitted->status)->toBe(OrderStatus::Filled);
});

it('the raw override wins over the enum status on the wire', function (): void {
    $order = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);
    (new EmitUnknownSpotOrderStatus)->handle($order, 'SOME_FUTURE_STATE');

    $wire = SpotOrderView::fromModel($order->refresh())->toWireArray(withFills: false);

    expect($wire['status'])->toBe('SOME_FUTURE_STATE');
});
