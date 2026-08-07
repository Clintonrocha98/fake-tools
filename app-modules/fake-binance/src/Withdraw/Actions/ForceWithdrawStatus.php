<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Actions;

use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

/**
 * Cenário do painel: força um dos três status terminais de falha
 * (Cancelled/Rejected/Failure — 1/3/5) com `info` preenchido. Diferente de
 * `FiatOrder`, o withdraw não tem máscara de leitura: estes status já são
 * terminais para {@see AdvanceWithdrawStatus}, então gravar direto em
 * `status` nunca conflita com o avanço lazy. Limpa `raw_status_override`: os
 * dois overrides são mutuamente exclusivos.
 */
final readonly class ForceWithdrawStatus
{
    public function handle(Withdrawal $withdrawal, WithdrawStatus $status, ?string $info): Withdrawal
    {
        $from = $withdrawal->status;

        $withdrawal->update([
            'status' => $status,
            'info' => $info,
            'raw_status_override' => null,
        ]);

        $withdrawal->refresh();

        BinanceLog::info('fake-binance.withdraw: status forçado por cenário — o relógio é ignorado de propósito, para exercitar um ramo que o consumidor trata mas raramente vê', [
            'withdrawal_id' => $withdrawal->id,
            'withdraw_order_id' => $withdrawal->withdraw_order_id,
            'from' => $from->value,
            'to' => $status->value,
        ]);

        return $withdrawal;
    }
}
