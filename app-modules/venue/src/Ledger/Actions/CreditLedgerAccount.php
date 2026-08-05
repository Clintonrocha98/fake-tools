<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Actions;

use He4rt\Venue\Ledger\Models\LedgerAccount;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Credita `amount` no saldo livre de `asset` (normalizado para maiúsculas — única
 * fronteira que decide o case do código do ativo), criando a conta do ledger se ainda
 * não existir. A leitura+escrita do saldo corre dentro de uma transação com lock de
 * linha, para que créditos concorrentes no mesmo asset já existente nunca pisem um no
 * outro; a corrida de criação de uma conta nova é resolvida por savepoint — a inserção
 * perdedora captura a violação de unicidade e relê a linha da vencedora com lock.
 */
final readonly class CreditLedgerAccount
{
    /**
     * @param  numeric-string  $amount
     */
    public function handle(string $asset, string $amount): LedgerAccount
    {
        $asset = mb_strtoupper($asset);

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

        try {
            DB::transaction(fn () => LedgerAccount::query()->create(['asset' => $asset, 'free' => '0', 'locked' => '0']));
        } catch (QueryException) {
            // A criação perdeu a corrida para outra transação concorrente no mesmo
            // asset novo: o savepoint acima isola o erro sem abortar a transação
            // externa, e a releitura abaixo com lock pega a linha da vencedora.
        }

        return LedgerAccount::query()->where('asset', $asset)->lockForUpdate()->firstOrFail();
    }
}
