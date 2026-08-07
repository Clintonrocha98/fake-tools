<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Actions;

use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

/**
 * Cenário do painel: congela/descongela um withdraw — enquanto `frozen`, o
 * avanço lazy ({@see AdvanceWithdrawStatus}) nunca calcula um novo status pela
 * idade desde `applied_at`.
 */
final readonly class SetWithdrawFrozen
{
    public function handle(Withdrawal $withdrawal, bool $frozen): Withdrawal
    {
        $withdrawal->update(['frozen' => $frozen]);

        BinanceLog::info('fake-binance.withdraw: congelamento de cenário alterado — enquanto congelado, nenhuma leitura move o status', [
            'withdrawal_id' => $withdrawal->id,
            'withdraw_order_id' => $withdrawal->withdraw_order_id,
            'frozen' => $frozen,
            'status' => $withdrawal->status->value,
        ]);

        return $withdrawal->refresh();
    }
}
