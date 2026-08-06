<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\DTOs;

use He4rt\FakeStarkbank\Support\WireTags;
use He4rt\FakeStarkbank\Support\WireTimestamp;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use JsonSerializable;

/**
 * O shape de uma transfer na wire, em camelCase. Um só para os três usos — o
 * eco de `POST /v2/transfer`, a releitura de `GET /v2/transfer/{id}` e cada
 * item da listagem —, porque os três fixtures do consumidor têm exatamente as
 * mesmas keys. A assimetria gordo/magro é da invoice, não desta perna.
 *
 * `branchCode` e `accountNumber` NÃO saem aqui: os blobs entram no POST e
 * ficam no registro; o StarkBank não os ecoa de volta, e devolvê-los
 * transformaria um dado opaco de ida num campo que algum consumidor futuro
 * passaria a ler.
 */
final readonly class TransferView implements JsonSerializable
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $name,
        public string $taxId,
        public string $bankCode,
        public string $accountType,
        public string $status,
        public WireTags $tags,
        public string $created,
        public string $updated,
    ) {}

    public static function fromModel(Transfer $transfer): self
    {
        return new self(
            id: $transfer->id,
            amount: $transfer->amount,
            name: $transfer->name,
            taxId: $transfer->tax_id,
            bankCode: $transfer->bank_code,
            accountType: $transfer->account_type,
            status: $transfer->status->value,
            tags: $transfer->tags,
            created: WireTimestamp::format($transfer->created_at),
            updated: WireTimestamp::format($transfer->updated_at),
        );
    }

    /**
     * @return array{id: string, amount: int, name: string, taxId: string, bankCode: string, accountType: string, status: string, tags: list<string>, created: string, updated: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'name' => $this->name,
            'taxId' => $this->taxId,
            'bankCode' => $this->bankCode,
            'accountType' => $this->accountType,
            'status' => $this->status,
            'tags' => $this->tags->toArray(),
            'created' => $this->created,
            'updated' => $this->updated,
        ];
    }
}
