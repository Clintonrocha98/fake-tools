<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Actions;

use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeBinance\Withdraw\Support\SyntheticTxId;
use Illuminate\Support\Facades\Date;

/**
 * Avanço automático LAZY do ciclo de vida (2 Awaiting Approval → 4 Processing →
 * 6 Completed): só roda quando o histórico é lido, nunca por scheduler — a idade
 * do withdraw desde `applied_at` contra `fake-binance-withdraw.advance_seconds` decide
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
    public function handle(Withdrawal $withdrawal): Withdrawal
    {
        if (!$withdrawal->status->advancesAutomatically()) {
            return $withdrawal;
        }

        if ($withdrawal->frozen) {
            BinanceLog::debug('fake-binance.withdraw: avanço lazy pulado — congelado por cenário, o estado fica exatamente onde o operador o deixou', [
                'withdrawal_id' => $withdrawal->id,
                'withdraw_order_id' => $withdrawal->withdraw_order_id,
                'status' => $withdrawal->status->value,
            ]);

            return $withdrawal;
        }

        $advanceSeconds = config()->integer('fake-binance-withdraw.advance_seconds');

        if ($advanceSeconds <= 0) {
            return $withdrawal;
        }

        $elapsedSeconds = $withdrawal->applied_at->diffInSeconds(Date::now());

        if ($elapsedSeconds >= $advanceSeconds * 2) {
            $from = $withdrawal->status;

            $withdrawal->update([
                'status' => WithdrawStatus::Completed,
                'tx_id' => SyntheticTxId::generate(),
                'completed_at' => Date::now(),
            ]);

            BinanceLog::info('fake-binance.withdraw: status avançado na leitura — o fake não tem scheduler, então é o próprio GET do consumidor que faz o tempo passar', [
                'withdrawal_id' => $withdrawal->id,
                'withdraw_order_id' => $withdrawal->withdraw_order_id,
                'coin' => $withdrawal->coin,
                'from' => $from->value,
                'to' => WithdrawStatus::Completed->value,
            ]);

            return $withdrawal;
        }

        if ($elapsedSeconds >= $advanceSeconds && $withdrawal->status === WithdrawStatus::AwaitingApproval) {
            $withdrawal->update(['status' => WithdrawStatus::Processing]);

            BinanceLog::info('fake-binance.withdraw: status avançado na leitura — o fake não tem scheduler, então é o próprio GET do consumidor que faz o tempo passar', [
                'withdrawal_id' => $withdrawal->id,
                'withdraw_order_id' => $withdrawal->withdraw_order_id,
                'coin' => $withdrawal->coin,
                'from' => WithdrawStatus::AwaitingApproval->value,
                'to' => WithdrawStatus::Processing->value,
            ]);
        }

        return $withdrawal;
    }
}
