<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\DTOs\FiatWithdrawalData;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Exceptions\FiatWithdrawRefusedException;
use He4rt\FakeBinance\Fiat\Models\FiatWithdrawal;
use He4rt\FakeBinance\Ledger\Actions\DebitLedgerAccount;
use He4rt\FakeBinance\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\FakeBinance\Support\BinanceLog;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * POST /sapi/v2/fiat/withdraw: debita o `amount` inteiro do ledger BRL no
 * aceite e cria o saque em `Processing` (ADR-0003) — o dinheiro sai da conta
 * na hora, como a Binance real congela o valor do PIX de saída. Toda recusa é
 * síncrona no envelope fiat HTTP 200 (ADR-0001).
 *
 * Idempotente por `clientOrderId`: repetir o mesmo id devolve o saque já
 * criado sem debitar de novo — nunca reprocessa nem revalida saldo na
 * repetição.
 */
final readonly class RequestFiatWithdrawal
{
    public function __construct(
        private DebitLedgerAccount $debit = new DebitLedgerAccount,
    ) {}

    public function handle(FiatWithdrawalData $data): FiatWithdrawal
    {
        $existing = FiatWithdrawal::query()->where('client_order_id', $data->clientOrderId)->first();

        if ($existing instanceof FiatWithdrawal) {
            BinanceLog::info('fake-binance.fiat: withdraw idempotente — clientOrderId repetido, devolvendo o saque existente sem debitar', [
                'client_order_id' => $data->clientOrderId,
                'order_id' => $existing->order_id,
            ]);

            return $existing;
        }

        $this->guardSupportedCurrencyAndMethod($data->currency, $data->paymentMethod);

        $currency = mb_strtoupper($data->currency);
        $amount = $data->amount;

        // A FormRequest já validou o formato decimal; este guard só narrowa o
        // tipo para o ledger — nunca deve disparar num request que passou por ela.
        throw_unless(is_numeric($amount), RuntimeException::class, 'amount must be a decimal string.');

        return DB::transaction(function () use ($data, $currency, $amount): FiatWithdrawal {
            try {
                $this->debit->handle($currency, $amount);
            } catch (InsufficientLedgerBalanceException) {
                throw FiatWithdrawRefusedException::insufficientBalance($currency, $amount);
            }

            $withdrawal = FiatWithdrawal::query()->create([
                'order_id' => (string) Str::uuid(),
                'currency' => $currency,
                'payment_method' => $data->paymentMethod,
                'amount' => $data->amount,
                'account_number' => $data->accountNumber,
                'agency' => $data->agency,
                'bank_code_for_pix' => $data->bankCodeForPix,
                'account_type' => $data->accountType,
                'client_order_id' => $data->clientOrderId,
                'status' => FiatOrderStatus::Processing,
                'requested_at' => Date::now(),
            ]);

            BinanceLog::info('fake-binance.fiat: withdraw aceito — BRL debitado do ledger no aceite', [
                'order_id' => $withdrawal->order_id,
                'client_order_id' => $data->clientOrderId,
                'currency' => $currency,
                'amount' => $data->amount,
                'account_number' => $data->accountNumber,
            ]);

            return $withdrawal;
        });
    }

    private function guardSupportedCurrencyAndMethod(string $currency, string $paymentMethod): void
    {
        $supportedCurrency = config()->string('fake-binance-fiat.supported_currency', 'BRL');
        $supportedMethod = config()->string('fake-binance-fiat.withdraw_payment_method', 'bank_transfer');

        if (mb_strtoupper($currency) !== mb_strtoupper($supportedCurrency)
            || mb_strtolower($paymentMethod) !== mb_strtolower($supportedMethod)) {
            throw FiatWithdrawRefusedException::unsupportedCurrencyOrMethod($currency, $paymentMethod);
        }
    }
}
