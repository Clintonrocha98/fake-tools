<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Actions;

use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use He4rt\Venue\Withdraw\Support\SyntheticTxId;
use Illuminate\Support\Facades\Date;

/**
 * Avanço automático LAZY do ciclo de vida (2 Awaiting Approval → 4 Processing →
 * 6 Completed): só roda quando o histórico é lido, nunca por scheduler — a idade
 * do withdraw desde `applied_at` contra `venue-withdraw.advance_seconds` decide
 * quantos estágios já se passaram. Um withdraw lido bem depois do segundo
 * intervalo salta direto para Completed, sem passar por Processing como estado
 * persistido intermediário.
 *
 * Nunca mexe num status fora de {AwaitingApproval, Processing}: falhas (Cancelled/
 * Rejected/Failure) e overrides manuais são sempre terminais para este avanço.
 * Um withdraw congelado no painel (`frozen`) também nunca avança sozinho.
 */
final readonly class AdvanceWithdrawStatus
{
    public function __invoke(Withdrawal $withdrawal): Withdrawal
    {
        if ($withdrawal->frozen || !$withdrawal->status->advancesAutomatically()) {
            return $withdrawal;
        }

        $advanceSeconds = config()->integer('venue-withdraw.advance_seconds');

        if ($advanceSeconds <= 0) {
            return $withdrawal;
        }

        $elapsedSeconds = $withdrawal->applied_at->diffInSeconds(Date::now());

        if ($elapsedSeconds >= $advanceSeconds * 2) {
            $withdrawal->update([
                'status' => WithdrawStatus::Completed,
                'tx_id' => SyntheticTxId::generate(),
            ]);

            return $withdrawal;
        }

        if ($elapsedSeconds >= $advanceSeconds && $withdrawal->status === WithdrawStatus::AwaitingApproval) {
            $withdrawal->update(['status' => WithdrawStatus::Processing]);
        }

        return $withdrawal;
    }
}
