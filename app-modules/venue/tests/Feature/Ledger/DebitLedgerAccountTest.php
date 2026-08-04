<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Ledger\Actions\DebitLedgerAccount;
use He4rt\Venue\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\Venue\Ledger\Models\LedgerAccount;

it('subtracts the amount from the free balance', function (): void {
    (new CreditLedgerAccount)('USDC', '100');

    $account = (new DebitLedgerAccount)('USDC', '30');

    expect($account->free)->toBe('70.000000000000000000');
});

it('debits amount+fee as a single already-summed value', function (): void {
    (new CreditLedgerAccount)('USDC', '100');

    // O caller (ticket do withdraw) já soma taxa e valor antes de chamar o ledger.
    $account = (new DebitLedgerAccount)('USDC', '31.5');

    expect($account->free)->toBe('68.500000000000000000');
});

it('throws when the asset has no ledger account at all', function (): void {
    (new DebitLedgerAccount)('BRL', '1');
})->throws(InsufficientLedgerBalanceException::class);

it('throws when the requested amount exceeds the free balance', function (): void {
    (new CreditLedgerAccount)('BRL', '10');

    (new DebitLedgerAccount)('BRL', '10.01');
})->throws(InsufficientLedgerBalanceException::class);

it('leaves the balance untouched when a debit is rejected', function (): void {
    (new CreditLedgerAccount)('BRL', '10');

    try {
        (new DebitLedgerAccount)('BRL', '999');
    } catch (InsufficientLedgerBalanceException) {
        // esperado
    }

    $account = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($account->free)->toBe('10.000000000000000000');
});

it('allows debiting the exact available balance down to zero', function (): void {
    (new CreditLedgerAccount)('BRL', '10');

    $account = (new DebitLedgerAccount)('BRL', '10');

    expect($account->free)->toBe('0.000000000000000000');
});

it('treats an asset code as case-insensitive when debiting', function (): void {
    (new CreditLedgerAccount)('USDC', '100');

    $account = (new DebitLedgerAccount)('usdc', '40');

    expect($account->asset)->toBe('USDC')
        ->and($account->free)->toBe('60.000000000000000000');
});
