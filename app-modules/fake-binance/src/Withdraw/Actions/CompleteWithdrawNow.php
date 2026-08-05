<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Actions;

use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeBinance\Withdraw\Support\SyntheticTxId;

/**
 * Cenário do painel: "completar agora", pulando o relógio do avanço lazy —
 * leva `status` direto a {@see WithdrawStatus::Completed} com um `txId`
 * sintético, limpando qualquer `raw_status_override` pendente.
 */
final readonly class CompleteWithdrawNow
{
    public function handle(Withdrawal $withdrawal): Withdrawal
    {
        $withdrawal->update([
            'status' => WithdrawStatus::Completed,
            'tx_id' => SyntheticTxId::generate(),
            'raw_status_override' => null,
        ]);

        return $withdrawal->refresh();
    }
}
