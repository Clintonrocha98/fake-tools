<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\Actions;

use He4rt\FakeBinance\Ledger\DTOs\AccountBalance;
use He4rt\FakeBinance\Ledger\DTOs\AccountSnapshot;
use He4rt\FakeBinance\Ledger\DTOs\CommissionRates;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use Illuminate\Support\Facades\Date;

final readonly class GetAccountSnapshot
{
    public function handle(bool $omitZeroBalances = false): AccountSnapshot
    {
        $balances = array_values(LedgerAccount::query()
            ->orderBy('asset')
            ->get()
            ->reject(fn (LedgerAccount $account): bool => $omitZeroBalances && $this->isZero($account))
            ->map(fn (LedgerAccount $account): AccountBalance => new AccountBalance(
                asset: $account->asset,
                free: LedgerAmount::wire($account->free),
                locked: LedgerAmount::wire($account->locked),
            ))
            ->all());

        // As comissões do account espelham a taxa que a execução spot cobra de
        // fato: basis points nos campos legados (0.001 → 10), decimal-string
        // de 8 casas em commissionRates — nunca dois números diferentes para a
        // mesma taxa.
        $commissionRate = config()->string('fake-binance-spot.commission_rate');
        $commissionBps = is_numeric($commissionRate) ? (int) bcmul($commissionRate, '10000', 0) : 0;
        $commissionWire = is_numeric($commissionRate) ? bcadd($commissionRate, '0', 8) : '0.00000000';

        return new AccountSnapshot(
            balances: $balances,
            updateTime: Date::now()->getTimestampMs(),
            commissionRates: new CommissionRates(maker: $commissionWire, taker: $commissionWire),
            makerCommission: $commissionBps,
            takerCommission: $commissionBps,
        );
    }

    private function isZero(LedgerAccount $account): bool
    {
        return bccomp($account->free, '0', 18) === 0 && bccomp($account->locked, '0', 18) === 0;
    }
}
