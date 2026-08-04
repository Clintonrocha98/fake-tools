<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Actions;

use He4rt\Venue\Ledger\DTOs\AccountBalance;
use He4rt\Venue\Ledger\DTOs\AccountSnapshot;
use He4rt\Venue\Ledger\Models\LedgerAccount;
use He4rt\Venue\Ledger\Support\LedgerAmount;
use Illuminate\Support\Facades\Date;

final readonly class GetAccountSnapshot
{
    public function __invoke(bool $omitZeroBalances = false): AccountSnapshot
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

        return new AccountSnapshot(
            balances: $balances,
            updateTime: Date::now()->getTimestampMs(),
        );
    }

    private function isZero(LedgerAccount $account): bool
    {
        return bccomp($account->free, '0', 18) === 0 && bccomp($account->locked, '0', 18) === 0;
    }
}
