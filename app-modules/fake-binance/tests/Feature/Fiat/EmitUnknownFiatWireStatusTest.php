<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Actions\EmitUnknownFiatWireStatus;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;

it('sets an arbitrary wire status', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $emitted = (new EmitUnknownFiatWireStatus)->handle($order, 'ORDER_FUTURE_STATE');

    expect($emitted->forced_wire_status)->toBe('ORDER_FUTURE_STATE');
});

it('clears any forced_status: the two overrides are mutually exclusive', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Failed,
    ]);

    $emitted = (new EmitUnknownFiatWireStatus)->handle($order, 'ORDER_FUTURE_STATE');

    expect($emitted->forced_status)->toBeNull()
        ->and($emitted->forced_wire_status)->toBe('ORDER_FUTURE_STATE');
});
