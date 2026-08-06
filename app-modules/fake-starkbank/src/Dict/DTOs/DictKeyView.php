<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Dict\DTOs;

use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use JsonSerializable;

/**
 * O conteúdo do envelope `{"key": {...}}` de `GET /v2/dict-key/{key}`, no
 * camelCase da wire.
 *
 * O `id` da wire é a própria chave PIX — não a pk da linha. `DictKeyResponse`
 * do consumidor lê `id` como `pixKey`, e devolver aqui o uuid interno faria o
 * `POST /v2/transfer` seguinte nomear um beneficiário que não existe.
 */
final readonly class DictKeyView implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $taxId,
        public string $ownerType,
        public string $bankName,
        public string $ispb,
        public string $branchCode,
        public string $accountNumber,
        public string $accountType,
        public string $status,
    ) {}

    public static function fromModel(DictEntry $entry): self
    {
        return new self(
            id: $entry->pix_key,
            type: $entry->type->value,
            name: $entry->name,
            taxId: $entry->tax_id,
            ownerType: $entry->owner_type->value,
            bankName: $entry->bank_name,
            ispb: $entry->ispb,
            branchCode: $entry->branch_code_blob,
            accountNumber: $entry->account_number_blob,
            accountType: $entry->account_type,
            status: $entry->status,
        );
    }

    /**
     * @return array{id: string, type: string, name: string, taxId: string, ownerType: string, bankName: string, ispb: string, branchCode: string, accountNumber: string, accountType: string, status: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'taxId' => $this->taxId,
            'ownerType' => $this->ownerType,
            'bankName' => $this->bankName,
            'ispb' => $this->ispb,
            'branchCode' => $this->branchCode,
            'accountNumber' => $this->accountNumber,
            'accountType' => $this->accountType,
            'status' => $this->status,
        ];
    }
}
