<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Actions;

use He4rt\Venue\Ledger\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;

/**
 * Credita `amount` no saldo livre de `asset`, criando a conta do ledger se ainda não
 * existir. Toda a leitura+escrita corre dentro de uma transação com lock de linha, para
 * que créditos concorrentes no mesmo asset nunca pisem um no outro.
 */
final readonly class CreditLedgerAccount
{
    /**
     * @param  numeric-string  $amount
     */
    public function __invoke(string $asset, string $amount): LedgerAccount
    {
        return DB::transaction(function () use ($asset, $amount): LedgerAccount {
            $account = $this->lockedAccount($asset);

            $account->update([
                'free' => bcadd($account->free, $amount, 18),
            ]);

            return $account->refresh();
        });
    }

    private function lockedAccount(string $asset): LedgerAccount
    {
        $account = LedgerAccount::query()->where('asset', $asset)->lockForUpdate()->first();

        if ($account instanceof LedgerAccount) {
            return $account;
        }

        LedgerAccount::query()->create(['asset' => $asset, 'free' => '0', 'locked' => '0']);

        /** @var LedgerAccount $account */
        $account = LedgerAccount::query()->where('asset', $asset)->lockForUpdate()->firstOrFail();

        return $account;
    }
}
