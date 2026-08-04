<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\DTOs;

use He4rt\Venue\Ledger\Support\LedgerAmount;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use JsonSerializable;

/**
 * O shape EXATO de um item de GET /sapi/v1/capital/withdraw/history — `applyTime`
 * é sempre UTC (a Binance real nunca converte para o timezone do chamador; quem lê
 * é `treasury:reconcile-offramp-withdraws`, uma máquina, não uma tela). `status`
 * ecoa `raw_status_override` quando setado — vocabulário fora de `WithdrawStatus`
 * (0-6), cenário do painel para provar o fail-closed do monolito consumidor.
 */
final readonly class WithdrawHistoryRow implements JsonSerializable
{
    public function __construct(
        public string $id,
        public ?string $withdrawOrderId,
        public string $coin,
        public string $network,
        public string $address,
        public string $amount,
        public string $transactionFee,
        public int $status,
        public ?string $txId,
        public ?string $info,
        public string $applyTime,
    ) {}

    public static function fromModel(Withdrawal $withdrawal): self
    {
        return new self(
            id: $withdrawal->id,
            withdrawOrderId: $withdrawal->withdraw_order_id,
            coin: $withdrawal->coin,
            network: $withdrawal->network,
            address: $withdrawal->address,
            amount: LedgerAmount::wire((string) $withdrawal->amount),
            transactionFee: LedgerAmount::wire((string) $withdrawal->transaction_fee),
            status: $withdrawal->raw_status_override ?? $withdrawal->status->value,
            txId: $withdrawal->tx_id,
            info: $withdrawal->info,
            applyTime: $withdrawal->applied_at->clone()->utc()->format('Y-m-d H:i:s'),
        );
    }

    /**
     * @return array{id: string, withdrawOrderId: ?string, coin: string, network: string, address: string, amount: string, transactionFee: string, status: int, txId: ?string, info: ?string, applyTime: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'withdrawOrderId' => $this->withdrawOrderId,
            'coin' => $this->coin,
            'network' => $this->network,
            'address' => $this->address,
            'amount' => $this->amount,
            'transactionFee' => $this->transactionFee,
            'status' => $this->status,
            'txId' => $this->txId,
            'info' => $this->info,
            'applyTime' => $this->applyTime,
        ];
    }
}
