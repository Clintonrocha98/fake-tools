<?php

declare(strict_types=1);

use He4rt\Venue\Database\Seeders\LedgerAccountSeeder;
use He4rt\Venue\Ledger\Models\LedgerAccount;

it('seeds ledger accounts from the FAKE_BINANCE_SEED_BALANCES-backed config', function (): void {
    config(['venue-ledger.seed_balances' => 'BRL:100000,USDC:0']);

    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::query()->where('asset', 'BRL')->firstOrFail()->free)->toBe('100000.000000000000000000');
    expect(LedgerAccount::query()->where('asset', 'USDC')->firstOrFail()->free)->toBe('0.000000000000000000');
});

it('seeds nothing when the config value is not set', function (): void {
    config(['venue-ledger.seed_balances' => null]);

    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::query()->count())->toBe(0);
});
