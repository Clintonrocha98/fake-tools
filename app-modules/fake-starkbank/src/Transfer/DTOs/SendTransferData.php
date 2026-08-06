<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\DTOs;

use He4rt\FakeStarkbank\Support\WireTags;

/**
 * Um item do array `transfers` do `POST /v2/transfer`, já normalizado.
 *
 * `branchCode` e `accountNumber` entram como strings quaisquer, sem validação
 * de formato: são os blobs que o DICT emitiu e o consumidor ecoa verbatim —
 * opacos por contrato, e o fake não é quem os emitiu quando a entry não veio do
 * seed. Validar o formato aqui recusaria um beneficiário legítimo.
 */
final readonly class SendTransferData
{
    /**
     * @param  int  $amount  centavos
     * @param  string  $bankCode  ISPB de 8 dígitos — é ele que seleciona o trilho PIX
     * @param  list<string>  $tags
     */
    public function __construct(
        public int $amount,
        public string $name,
        public string $taxId,
        public string $bankCode,
        public string $branchCode,
        public string $accountNumber,
        public string $accountType,
        public ?string $externalId,
        public array $tags,
    ) {}

    /**
     * @param  array<array-key, mixed>  $item
     */
    public static function fromWire(array $item): self
    {
        $externalId = $item['externalId'] ?? null;
        $tags = $item['tags'] ?? [];

        return new self(
            amount: (int) ($item['amount'] ?? 0),
            name: (string) ($item['name'] ?? ''),
            taxId: (string) ($item['taxId'] ?? ''),
            bankCode: (string) ($item['bankCode'] ?? ''),
            branchCode: (string) ($item['branchCode'] ?? ''),
            accountNumber: (string) ($item['accountNumber'] ?? ''),
            // A doc trata `accountType` como opcional e assume conta corrente;
            // o consumidor sempre manda o que o DICT resolveu.
            accountType: is_string($item['accountType'] ?? null) && $item['accountType'] !== ''
                ? $item['accountType']
                : 'checking',
            externalId: is_string($externalId) && $externalId !== '' ? $externalId : null,
            tags: WireTags::fromArray(is_array($tags) ? $tags : [])->toArray(),
        );
    }
}
