<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Actions;

use He4rt\FakeBinance\Deposit\Enums\DepositStatus;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Avanço automático LAZY do ciclo de vida (0 Pending → 6 Credited → 1 Success):
 * só roda quando o hisrec é lido, nunca por scheduler — a idade do depósito
 * desde `announced_at` contra `fake-binance-deposit.advance_seconds` decide
 * quantos estágios já se passaram. O ledger é creditado ao ENTRAR em Credited
 * — é quando a Binance real torna o saldo negociável (a conversão SELL do
 * fluxo inverso já pode rodar) — e no máximo UMA vez: `credited_at` é o guard,
 * então um depósito lido bem depois do segundo intervalo salta direto para
 * Success creditando uma única vez no caminho.
 *
 * Nunca mexe num status fora de {Pending, Credited}: WrongDeposit e
 * WaitingUserConfirm são manuais, Success é terminal.
 */
final readonly class AdvanceCryptoDepositStatus
{
    public function __construct(
        private CreditLedgerAccount $credit = new CreditLedgerAccount,
    ) {}

    public function handle(CryptoDeposit $deposit): CryptoDeposit
    {
        if (!$deposit->status->advancesAutomatically()) {
            return $deposit;
        }

        $advanceSeconds = config()->integer('fake-binance-deposit.advance_seconds');

        if ($advanceSeconds <= 0) {
            return $deposit;
        }

        $elapsedSeconds = $deposit->announced_at->diffInSeconds(Date::now());

        if ($elapsedSeconds < $advanceSeconds) {
            return $deposit;
        }

        $target = $elapsedSeconds >= $advanceSeconds * 2 ? DepositStatus::Success : DepositStatus::Credited;

        return DB::transaction(function () use ($deposit, $target): CryptoDeposit {
            /** @var CryptoDeposit $locked */
            $locked = CryptoDeposit::query()->whereKey($deposit->id)->lockForUpdate()->firstOrFail();

            if (!$locked->status->advancesAutomatically()) {
                return $locked;
            }

            $from = $locked->status;

            $updates = ['status' => $target];

            if ($locked->credited_at === null) {
                $this->credit->handle($locked->coin, $locked->amount);
                $updates['credited_at'] = Date::now();

                BinanceLog::info('fake-binance.deposit: ledger creditado pelo avanço lazy — o saldo já pode ser convertido', [
                    'deposit_id' => $locked->id,
                    'coin' => $locked->coin,
                    'amount' => $locked->amount,
                    'status' => $target->value,
                ]);
            }

            $locked->update($updates);

            BinanceLog::info('fake-binance.deposit: status avançado na leitura — o fake não tem scheduler, então é o próprio GET do consumidor que faz o tempo passar', [
                'deposit_id' => $locked->id,
                'coin' => $locked->coin,
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $locked->refresh();
        });
    }
}
