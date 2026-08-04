<?php

declare(strict_types=1);

use He4rt\Venue\Spot\Models\SpotOrder;

it('exposes the wire fills without shadowing an Eloquent relation', function (): void {
    $order = SpotOrder::factory()->make();

    expect($order->wireFills())->toBeArray()
        ->and(fn () => $order->fills)->not->toThrow(LogicException::class);
});
