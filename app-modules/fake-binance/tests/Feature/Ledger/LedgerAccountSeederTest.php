<?php

declare(strict_types=1);

use He4rt\FakeBinance\Database\Seeders\LedgerAccountSeeder;
use He4rt\FakeBinance\Ledger\Actions\DebitLedgerAccount;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;

it('seeds ledger accounts from the FAKE_BINANCE_SEED_BALANCES-backed config', function (): void {
    config(['fake-binance-ledger.seed_balances' => 'BRL:100000,USDC:0']);

    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::query()->where('asset', 'BRL')->firstOrFail()->free)->toBe('100000.000000000000000000');
    expect(LedgerAccount::query()->where('asset', 'USDC')->firstOrFail()->free)->toBe('0.000000000000000000');
});

it('seeds nothing when the config value is not set', function (): void {
    config(['fake-binance-ledger.seed_balances' => null]);

    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::query()->count())->toBe(0);
});

it('never credits twice when the seeder runs again over a ledger that already has accounts', function (): void {
    // O entrypoint do container roda `db:seed` em TODO start, sem marcador
    // externo — o guard do seeder é o que impede o restart de dobrar os saldos.
    config(['fake-binance-ledger.seed_balances' => 'BRL:100000']);

    $this->seed(LedgerAccountSeeder::class);
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::query()->where('asset', 'BRL')->firstOrFail()->free)->toBe('100000.000000000000000000');
});

it('leaves an evolved ledger untouched instead of topping it up on a later seed', function (): void {
    config(['fake-binance-ledger.seed_balances' => 'BRL:100000']);
    $this->seed(LedgerAccountSeeder::class);

    // O dev gastou BRL operando: um novo seed não deve "recarregar" o saldo.
    (new DebitLedgerAccount)->handle('BRL', '40000');
    $this->seed(LedgerAccountSeeder::class);

    expect(LedgerAccount::query()->where('asset', 'BRL')->firstOrFail()->free)->toBe('60000.000000000000000000');
});

it('seeds ledger balances through the default DatabaseSeeder entrypoint the container runs', function (): void {
    // O container sobe com `php artisan db:seed --force`, que roda o DatabaseSeeder
    // default — não `LedgerAccountSeeder` isolado. É esse caminho que precisa funcionar.
    config(['fake-binance-ledger.seed_balances' => 'BRL:100000,USDC:0']);

    $this->seed();

    expect(LedgerAccount::query()->where('asset', 'BRL')->firstOrFail()->free)->toBe('100000.000000000000000000');
    expect(LedgerAccount::query()->where('asset', 'USDC')->firstOrFail()->free)->toBe('0.000000000000000000');
});
