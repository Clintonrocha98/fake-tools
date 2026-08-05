<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\SetLedgerBalance;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;

it('creates the account when the asset does not exist yet', function (): void {
    $account = (new SetLedgerBalance)->handle('BRL', '1000', '50');

    expect($account->asset)->toBe('BRL')
        ->and((string) $account->free)->toBe('1000.000000000000000000')
        ->and((string) $account->locked)->toBe('50.000000000000000000');
});

it('overwrites free/locked on an existing account, unlike the delta-based Credit/Debit actions', function (): void {
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '999', 'locked' => '999']);

    $account = (new SetLedgerBalance)->handle('USDC', '10', '0');

    expect((string) $account->free)->toBe('10.000000000000000000')
        ->and((string) $account->locked)->toBe('0.000000000000000000');
    expect(LedgerAccount::query()->where('asset', 'USDC')->count())->toBe(1);
});

it('treats the asset code as case-insensitive', function (): void {
    $account = (new SetLedgerBalance)->handle('brl', '5', '0');

    expect($account->asset)->toBe('BRL');
});
