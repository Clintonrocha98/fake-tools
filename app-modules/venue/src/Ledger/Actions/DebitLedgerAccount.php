<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Actions;

use He4rt\Venue\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\Venue\Ledger\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;

/**
 * Debita `amount` do saldo livre de `asset` (normalizado para maiúsculas — mesma
 * fronteira de {@see CreditLedgerAccount}). Chamado com o total já somado — um
 * withdraw passa `amount + fee` como um único valor ({@see SwapLedgerAssets}
 * para o caso de swap, que soma múltiplos fills antes de debitar).
 */
final readonly class DebitLedgerAccount
{
    /**
     * @param  numeric-string  $amount
     */
    public function __invoke(string $asset, string $amount): LedgerAccount
    {
        $asset = mb_strtoupper($asset);

        return DB::transaction(function () use ($asset, $amount): LedgerAccount {
            $account = LedgerAccount::query()->where('asset', $asset)->lockForUpdate()->first();

            if (!$account instanceof LedgerAccount) {
                throw InsufficientLedgerBalanceException::forAsset($asset, $amount, '0');
            }

            if (bccomp((string) $account->free, $amount, 18) < 0) {
                throw InsufficientLedgerBalanceException::forAsset($asset, $amount, $account->free);
            }

            $account->update([
                'free' => bcsub((string) $account->free, $amount, 18),
            ]);

            return $account->refresh();
        });
    }
}
