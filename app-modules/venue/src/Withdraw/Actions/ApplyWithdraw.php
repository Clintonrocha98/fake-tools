<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Actions;

use He4rt\Venue\Ledger\Actions\DebitLedgerAccount;
use He4rt\Venue\Withdraw\DTOs\ApplyWithdrawData;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Exceptions\MisconfiguredWithdrawFeeException;
use He4rt\Venue\Withdraw\Exceptions\UnsupportedWithdrawNetworkException;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * POST /sapi/v1/capital/withdraw/apply: debita `amount + fee` do ledger e cria o
 * withdrawal em `AwaitingApproval` (status 2) — nunca cria o registro se o débito
 * falhar por saldo insuficiente ({@see \He4rt\Venue\Ledger\Exceptions\InsufficientLedgerBalanceException},
 * deixada propagar para o controller mapear no envelope de erro).
 *
 * Idempotente por `withdrawOrderId`: repetir o mesmo id devolve o withdrawal já
 * criado sem debitar de novo — nunca reprocessa nem revalida saldo na repetição.
 */
final readonly class ApplyWithdraw
{
    public function __construct(
        private DebitLedgerAccount $debit = new DebitLedgerAccount,
    ) {}

    public function __invoke(ApplyWithdrawData $data): Withdrawal
    {
        if ($data->withdrawOrderId !== null) {
            $existing = Withdrawal::query()->where('withdraw_order_id', $data->withdrawOrderId)->first();

            if ($existing instanceof Withdrawal) {
                return $existing;
            }
        }

        $coin = mb_strtoupper($data->coin);
        $network = mb_strtoupper($data->network);
        $fee = $this->feeFor($network);
        $total = bcadd($data->amount, $fee, 18);

        return DB::transaction(function () use ($data, $coin, $network, $fee, $total): Withdrawal {
            ($this->debit)($coin, $total);

            return Withdrawal::query()->create([
                'coin' => $coin,
                'network' => $network,
                'address' => $data->address,
                'address_tag' => $data->addressTag,
                'amount' => $data->amount,
                'transaction_fee' => $fee,
                'withdraw_order_id' => $data->withdrawOrderId,
                'status' => WithdrawStatus::AwaitingApproval,
                'applied_at' => Date::now(),
            ]);
        });
    }

    /**
     * @return numeric-string
     */
    private function feeFor(string $network): string
    {
        $fees = config()->array('venue-withdraw.fees');

        if (!array_key_exists($network, $fees)) {
            throw UnsupportedWithdrawNetworkException::forNetwork($network);
        }

        $fee = $fees[$network];

        if (!is_string($fee) || !is_numeric($fee)) {
            throw MisconfiguredWithdrawFeeException::forNetwork($network, is_string($fee) ? $fee : get_debug_type($fee));
        }

        return $fee;
    }
}
