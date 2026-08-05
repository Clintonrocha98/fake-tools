<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Models\LedgerAccount;
use He4rt\Venue\Spot\Actions\ExpireSpotOrderPartially;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;

it('halves the original fill and moves the order to EXPIRED', function (): void {
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '10']);
    LedgerAccount::factory()->create(['asset' => 'BRL', 'free' => '0']);

    $order = SpotOrder::factory()->create([
        'status' => OrderStatus::Filled,
        'executed_qty' => '2.93000000',
        'cummulative_quote_qty' => '14.97230000',
        'commission' => '0.00293000',
    ]);

    $expired = (new ExpireSpotOrderPartially)->handle($order);

    expect($expired->status)->toBe(OrderStatus::Expired)
        ->and((string) $expired->executed_qty)->toBe('1.465000000000000000')
        ->and((string) $expired->cummulative_quote_qty)->toBe('7.486150000000000000')
        ->and((string) $expired->commission)->toBe('0.001465000000000000');
});

it('clears any raw_status_override', function (): void {
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '10']);
    LedgerAccount::factory()->create(['asset' => 'BRL', 'free' => '0']);

    $order = SpotOrder::factory()->create(['raw_status_override' => 'SOME_FUTURE_STATE']);

    $expired = (new ExpireSpotOrderPartially)->handle($order);

    expect($expired->raw_status_override)->toBeNull();
});

it('reverses only the removed half of the ledger fill, leaving the other half credited', function (): void {
    // Ordem BUY: gastou 14.9723 BRL, recebeu 2.93 USDC bruto (2.92707 líquido) —
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

    (new ExpireSpotOrderPartially)->handle($order);

    // Metade líquida do fill original permanece creditada: USDC líquido
    // 1.463535 (metade de 2.92707), BRL 7.48615 devolvido dos 92.5 gastos.
    expect((string) LedgerAccount::query()->where('asset', 'USDC')->first()->free)->toBe('1.463535000000000000')
        ->and((string) LedgerAccount::query()->where('asset', 'BRL')->first()->free)->toBe('92.513850000000000000');
});
