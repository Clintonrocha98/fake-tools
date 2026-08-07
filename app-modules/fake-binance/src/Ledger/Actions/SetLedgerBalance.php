<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\Actions;

use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Support\Facades\DB;

/**
 * Cenário do painel: fixa `free`/`locked` de um asset num valor exato — ao
 * contrário de {@see CreditLedgerAccount}/{@see DebitLedgerAccount} (deltas
 * relativos), esta Action SOBRESCREVE o saldo. Cria a conta se ainda não
 * existir, para o painel poder semear um asset novo direto do formulário.
 */
final readonly class SetLedgerBalance
{
    /**
     * @param  numeric-string  $free
     * @param  numeric-string  $locked
     */
    public function handle(string $asset, string $free, string $locked): LedgerAccount
    {
        $asset = mb_strtoupper($asset);

        $account = DB::transaction(function () use ($asset, $free, $locked): LedgerAccount {
            $account = LedgerAccount::query()->where('asset', $asset)->lockForUpdate()->first();

            if (!$account instanceof LedgerAccount) {
                return LedgerAccount::query()->create(['asset' => $asset, 'free' => $free, 'locked' => $locked]);
            }

            $account->update(['free' => $free, 'locked' => $locked]);

            return $account->refresh();
        });

        BinanceLog::info('fake-binance.ledger: saldo fixado por comando do operador — sobrescreve free/locked no valor exato, ao contrário do crédito/débito relativo do fluxo normal', [
            'asset' => $asset,
            'free' => $free,
            'locked' => $locked,
        ]);

        return $account;
    }
}
