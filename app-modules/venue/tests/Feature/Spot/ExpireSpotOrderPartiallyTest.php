<?php

declare(strict_types=1);

use He4rt\Venue\Spot\Actions\ExpireSpotOrderPartially;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

it('halves the original fill and moves the order to EXPIRED', function (): void {
    $order = SpotOrder::factory()->create([
        'status' => OrderStatus::Filled,
        'executed_qty' => '2.93000000',
        'cummulative_quote_qty' => '14.97230000',
        'commission' => '0.00293000',
    ]);

    $expired = (new ExpireSpotOrderPartially)($order);

    expect($expired->status)->toBe(OrderStatus::Expired)
        ->and((string) $expired->executed_qty)->toBe('1.465000000000000000')
        ->and((string) $expired->cummulative_quote_qty)->toBe('7.486150000000000000')
        ->and((string) $expired->commission)->toBe('0.001465000000000000');
});

it('clears any raw_status_override', function (): void {
    $order = SpotOrder::factory()->create(['raw_status_override' => 'SOME_FUTURE_STATE']);

    $expired = (new ExpireSpotOrderPartially)($order);

    expect($expired->raw_status_override)->toBeNull();
});
