<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Actions;

use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeBinance\Withdraw\Support\SyntheticTxId;
use Illuminate\Support\Facades\Date;

/**
 * Cenário do painel: "completar agora", pulando o relógio do avanço lazy —
 * leva `status` direto a {@see WithdrawStatus::Completed} com um `txId`
 * sintético, limpando qualquer `raw_status_override` pendente.
 */
final readonly class CompleteWithdrawNow
{
    public function handle(Withdrawal $withdrawal): Withdrawal
    {
        $from = $withdrawal->status;

        $withdrawal->update([
            'status' => WithdrawStatus::Completed,
            'tx_id' => SyntheticTxId::forNetwork($withdrawal->network),
            'completed_at' => Date::now(),
            'raw_status_override' => null,
        ]);

        $withdrawal->refresh();

        BinanceLog::info('fake-binance.withdraw: conclusão forçada por cenário — o relógio do avanço lazy é ignorado de propósito, levando o withdraw direto a Completed com um txId sintético', [
            'withdrawal_id' => $withdrawal->id,
            'withdraw_order_id' => $withdrawal->withdraw_order_id,
            'from' => $from->value,
            'to' => WithdrawStatus::Completed->value,
            'tx_id' => $withdrawal->tx_id,
        ]);

        return $withdrawal;
    }
}
