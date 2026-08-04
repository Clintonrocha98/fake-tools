<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Actions;

use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

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
 */
final readonly class AdvanceWithdrawStatus
{
    public function __invoke(Withdrawal $withdrawal): Withdrawal
    {
        if (!$withdrawal->status->advancesAutomatically()) {
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
                'tx_id' => $this->syntheticTxId(),
            ]);

            return $withdrawal;
        }

        if ($elapsedSeconds >= $advanceSeconds && $withdrawal->status === WithdrawStatus::AwaitingApproval) {
            $withdrawal->update(['status' => WithdrawStatus::Processing]);
        }

        return $withdrawal;
    }

    private function syntheticTxId(): string
    {
        return '0x'.Str::lower(Str::random(64));
    }
}
