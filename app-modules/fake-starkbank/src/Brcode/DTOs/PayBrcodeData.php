<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\DTOs;

use He4rt\FakeStarkbank\Support\WireTags;

/**
 * Um item do array `payments` do `POST /v2/brcode-payment`, já normalizado.
 *
 * `hasExternalId` existe porque a AUSÊNCIA do campo é contrato: o endpoint de
 * brcode-payment é o único que recusa `externalId` (a correlação viaja só em
 * `tags`), e um DTO que simplesmente descartasse a chave desconhecida faria o
 * fake aceitar em silêncio o payload que o provedor recusa.
 */
final readonly class PayBrcodeData
{
    /**
     * @param  int  $amount  centavos
     * @param  string  $taxId  CPF/CNPJ do RECEBEDOR, como o consumidor o leu do preview
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $brcode,
        public string $taxId,
        public int $amount,
        public string $description,
        public array $tags,
        public bool $hasExternalId,
    ) {}

    /**
     * @param  array<array-key, mixed>  $item
     */
    public static function fromWire(array $item): self
    {
        $description = $item['description'] ?? '';
        $tags = $item['tags'] ?? [];

        return new self(
            brcode: (string) ($item['brcode'] ?? ''),
            taxId: (string) ($item['taxId'] ?? ''),
            amount: (int) ($item['amount'] ?? 0),
            description: is_string($description) ? mb_trim($description) : '',
            tags: WireTags::fromArray(is_array($tags) ? $tags : [])->toArray(),
            hasExternalId: array_key_exists('externalId', $item),
        );
    }
}
