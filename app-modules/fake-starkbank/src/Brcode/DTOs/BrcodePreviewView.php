<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\DTOs;

use JsonSerializable;

/**
 * Um item de `previews` em `GET /v2/brcode-preview`, no camelCase da wire.
 *
 * `taxId` vazio é resposta de verdade, não erro: significa "o recebedor deste
 * código não está no DICT deste fake". É esse vazio que dispara
 * `FundingNotSendable::destinationUnverifiable` do lado do consumidor — o fake
 * não recusa por ele, quem recusa é quem vai pagar.
 */
final readonly class BrcodePreviewView implements JsonSerializable
{
    public function __construct(
        public string $status,
        public string $name,
        public string $taxId,
        public string $bankCode,
        public string $accountType,
        public bool $allowChange,
        public int $amount,
        public int $nominalAmount,
        public string $reconciliationId,
        public string $description,
    ) {}

    /**
     * @return array{status: string, name: string, taxId: string, bankCode: string, accountType: string, allowChange: bool, amount: int, nominalAmount: int, reconciliationId: string, description: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'name' => $this->name,
            'taxId' => $this->taxId,
            'bankCode' => $this->bankCode,
            'accountType' => $this->accountType,
            'allowChange' => $this->allowChange,
            'amount' => $this->amount,
            'nominalAmount' => $this->nominalAmount,
            'reconciliationId' => $this->reconciliationId,
            'description' => $this->description,
        ];
    }
}
