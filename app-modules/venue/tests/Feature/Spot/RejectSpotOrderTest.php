<?php

declare(strict_types=1);

use He4rt\Venue\Spot\Actions\RejectSpotOrder;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

it('rejects an order, zeroing every fill field', function (): void {
    $order = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);

    $rejected = (new RejectSpotOrder)($order);

    expect($rejected->status)->toBe(OrderStatus::Rejected)
        ->and((string) $rejected->executed_qty)->toBe('0.000000000000000000')
        ->and((string) $rejected->cummulative_quote_qty)->toBe('0.000000000000000000')
        ->and($rejected->fill_price)->toBeNull()
        ->and((string) $rejected->commission)->toBe('0.000000000000000000')
        ->and($rejected->commission_asset)->toBeNull();
});

it('clears any raw_status_override', function (): void {
    $order = SpotOrder::factory()->create(['raw_status_override' => 'SOME_FUTURE_STATE']);

    $rejected = (new RejectSpotOrder)($order);

    expect($rejected->raw_status_override)->toBeNull();
});
