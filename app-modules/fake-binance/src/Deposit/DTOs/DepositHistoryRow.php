<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\DTOs;

use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use JsonSerializable;

/**
 * O shape EXATO de um item de GET /sapi/v1/capital/deposit/hisrec — o
 * `DepositHistoryResponse` do monolito consumidor lê `amount`, `coin`,
 * `network`, `address`, `txId?`, `status` e `insertTime`; o restante segue a
 * doc para o fake ser espelho da wire, com constantes onde o conceito não
 * existe (`transferType` 0 externo, `confirmTimes` 1/1, `walletType` 0).
 * `insertTime` e `completeTime` são epoch ms — no depósito a doc usa ms, não a
 * string datetime do withdraw/history.
 */
final readonly class DepositHistoryRow implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $amount,
        public string $coin,
        public string $network,
        public int $status,
        public string $address,
        public string $addressTag,
        public string $txId,
        public int $insertTime,
        public ?int $completeTime,
        public int $transferType = 0,
        public string $confirmTimes = '1/1',
        public int $unlockConfirm = 0,
        public int $walletType = 0,
    ) {}

    public static function fromModel(CryptoDeposit $deposit): self
    {
        return new self(
            id: $deposit->id,
            amount: LedgerAmount::wire((string) $deposit->amount),
            coin: $deposit->coin,
            network: $deposit->network,
            status: $deposit->status->value,
            address: $deposit->address,
            addressTag: $deposit->address_tag ?? '',
            txId: $deposit->tx_id,
            insertTime: $deposit->announced_at->getTimestampMs(),
            completeTime: $deposit->credited_at?->getTimestampMs(),
        );
    }

    /**
     * @return array{id: string, amount: string, coin: string, network: string, status: int, address: string, addressTag: string, txId: string, insertTime: int, completeTime: ?int, transferType: int, confirmTimes: string, unlockConfirm: int, walletType: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'coin' => $this->coin,
            'network' => $this->network,
            'status' => $this->status,
            'address' => $this->address,
            'addressTag' => $this->addressTag,
            'txId' => $this->txId,
            'insertTime' => $this->insertTime,
            'completeTime' => $this->completeTime,
            'transferType' => $this->transferType,
            'confirmTimes' => $this->confirmTimes,
            'unlockConfirm' => $this->unlockConfirm,
            'walletType' => $this->walletType,
        ];
    }
}
