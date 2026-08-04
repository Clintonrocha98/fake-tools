<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Ledger\Models\LedgerAccount;

it('creates the ledger account when crediting an asset for the first time', function (): void {
    $account = (new CreditLedgerAccount)('BRL', '100000');

    expect($account->asset)->toBe('BRL')
        ->and($account->free)->toBe('100000.000000000000000000')
        ->and($account->locked)->toBe('0.000000000000000000');

    expect(LedgerAccount::query()->where('asset', 'BRL')->count())->toBe(1);
});

it('adds to the existing free balance on a second credit', function (): void {
    $credit = new CreditLedgerAccount;

    $credit('USDC', '100');
    $account = $credit('USDC', '50.5');

    expect($account->free)->toBe('150.500000000000000000');
    expect(LedgerAccount::query()->where('asset', 'USDC')->count())->toBe(1);
});

it('never touches the locked balance', function (): void {
    $account = (new CreditLedgerAccount)('BRL', '10');

    expect($account->locked)->toBe('0.000000000000000000');
});
