<?php

declare(strict_types=1);

use He4rt\Venue\Fiat\Actions\CreditFiatOrderNow;
use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Ledger\Models\LedgerAccount;

it('credits a processing order immediately, skipping the lazy clock', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'currency' => 'BRL',
        'amount' => '500',
    ]);

    $credited = (new CreditFiatOrderNow)->handle($order);

    expect($credited->status)->toBe(FiatOrderStatus::Success)
        ->and($credited->credited_at)->not->toBeNull();

    $account = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    expect((string) $account->free)->toBe('500.000000000000000000');
});

it('clears any forced override before crediting', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'forced_status' => FiatOrderStatus::Failed,
        'forced_wire_status' => 'Some Future Status',
    ]);

    $credited = (new CreditFiatOrderNow)->handle($order);

    expect($credited->forced_status)->toBeNull()
        ->and($credited->forced_wire_status)->toBeNull()
        ->and($credited->status)->toBe(FiatOrderStatus::Success);
});

it('never credits twice on a repeated call', function (): void {
    $order = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Processing,
        'currency' => 'BRL',
        'amount' => '500',
    ]);

    $action = new CreditFiatOrderNow;
    $action->handle($order->refresh());
    $action->handle($order->refresh());

    $account = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();
    expect((string) $account->free)->toBe('500.000000000000000000');
});
