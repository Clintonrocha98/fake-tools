<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Actions;

use He4rt\Venue\Withdraw\Models\Withdrawal;

/**
 * Cenário do painel: emite um código de status inteiro fora de
 * `WithdrawStatus` (0-6) — prova o fail-closed do monolito consumidor
 * (`BinanceVenueWithdrawGateway::stateFor()` lança `UnexpectedValueException`
 * num código não mapeado). `status` na coluna real nunca muda: o cast de enum
 * explodiria num valor fora do conjunto, então o override vive numa coluna à
 * parte ({@see WithdrawHistoryRow::fromModel()} o prioriza na serialização).
 */
final readonly class EmitUnknownWithdrawStatus
{
    public function __invoke(Withdrawal $withdrawal, int $rawStatus): Withdrawal
    {
        $withdrawal->update(['raw_status_override' => $rawStatus]);

        return $withdrawal->refresh();
    }
}
