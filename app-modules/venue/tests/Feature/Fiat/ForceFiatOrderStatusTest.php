<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Actions\ForceFiatOrderStatus;
use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;

it('sets forced_status without touching the underlying lazy status', function (): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $forced = (new ForceFiatOrderStatus)->handle($order, FiatOrderStatus::Failed);

    expect($forced->forced_status)->toBe(FiatOrderStatus::Failed)
        ->and($forced->status)->toBe(FiatOrderStatus::Processing);
});

it('clears any forced_wire_status: the two overrides are mutually exclusive', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_wire_status' => 'Some Future Status',
    ]);

    $forced = (new ForceFiatOrderStatus)->handle($order, FiatOrderStatus::Expired);

    expect($forced->forced_wire_status)->toBeNull()
        ->and($forced->forced_status)->toBe(FiatOrderStatus::Expired);
});

it('produces every documented failure status on command', function (FiatOrderStatus $status): void {
    $order = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $forced = (new ForceFiatOrderStatus)->handle($order, $status);

    expect($forced->forced_status)->toBe($status);
})->with([
    'failed' => [FiatOrderStatus::Failed],
    'expired' => [FiatOrderStatus::Expired],
    'cancelled' => [FiatOrderStatus::Cancelled],
    'need additional action' => [FiatOrderStatus::NeedAdditionalAction],
]);
