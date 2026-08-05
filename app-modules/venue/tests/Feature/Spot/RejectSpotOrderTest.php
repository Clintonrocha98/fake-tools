<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Models\LedgerAccount;
use He4rt\Venue\Spot\Actions\RejectSpotOrder;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

it('rejects an order, zeroing every fill field', function (): void {
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '10']);
    LedgerAccount::factory()->create(['asset' => 'BRL', 'free' => '0']);

    $order = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);

    $rejected = (new RejectSpotOrder)->handle($order);

    expect($rejected->status)->toBe(OrderStatus::Rejected)
        ->and((string) $rejected->executed_qty)->toBe('0.000000000000000000')
        ->and((string) $rejected->cummulative_quote_qty)->toBe('0.000000000000000000')
        ->and($rejected->fill_price)->toBeNull()
        ->and((string) $rejected->commission)->toBe('0.000000000000000000')
        ->and($rejected->commission_asset)->toBeNull();
});

it('clears any raw_status_override', function (): void {
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '10']);
    LedgerAccount::factory()->create(['asset' => 'BRL', 'free' => '0']);

    $order = SpotOrder::factory()->create(['raw_status_override' => 'SOME_FUTURE_STATE']);

    $rejected = (new RejectSpotOrder)->handle($order);

    expect($rejected->raw_status_override)->toBeNull();
});

it('reverses the ledger fill it was originally credited with, so the account matches the now-empty order', function (): void {
    // Ordem BUY: gastou 14.9723 BRL, recebeu 2.93 USDC bruto (2.92707 líquido de comissão) —
    // exatamente o que PlaceMarketOrder teria creditado antes deste clique.
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '2.92707000']);
    LedgerAccount::factory()->create(['asset' => 'BRL', 'free' => '85.02770000']);

    $order = SpotOrder::factory()->create([
        'status' => OrderStatus::Filled,
        'executed_qty' => '2.93000000',
        'cummulative_quote_qty' => '14.97230000',
        'commission' => '0.00293000',
        'commission_asset' => 'USDC',
    ]);

    (new RejectSpotOrder)->handle($order);

    expect((string) LedgerAccount::query()->where('asset', 'USDC')->first()->free)->toBe('0.000000000000000000')
        ->and((string) LedgerAccount::query()->where('asset', 'BRL')->first()->free)->toBe('100.000000000000000000');
});

it('never touches the ledger when the order carries no fill to reverse', function (): void {
    $order = SpotOrder::factory()->rejected()->create();

    (new RejectSpotOrder)->handle($order);

    expect(LedgerAccount::query()->count())->toBe(0);
});
