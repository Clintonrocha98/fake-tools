<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Actions;

use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;

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
        $withdrawal->update([
            'status' => $status,
            'info' => $info,
            'raw_status_override' => null,
        ]);

        return $withdrawal->refresh();
    }
}
