<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\DTOs;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Support\WireTags;

/**
 * Um item do array `invoices` do `POST /v2/invoice`, já normalizado: o que a
 * wire manda em camelCase vira aqui um valor tipado, com o `due` sempre
 * resolvido.
 *
 * `due` ausente não acontece na prática — o consumidor sempre calcula
 * `now() + expiresInSeconds` —, mas a doc oficial assume `now() + 2 dias`
 * quando ele falta, e é esse default que o fake replica: um vencimento
 * inventado mais curto faria a invoice expirar antes do que o StarkBank real
 * faria.
 */
final readonly class IssueInvoiceData
{
    /**
     * @param  list<string>  $tags
     * @param  int  $expiration  segundos de graça DEPOIS do vencimento; 0 torna `due` prazo duro
     */
    public function __construct(
        public int $amount,
        public string $name,
        public string $taxId,
        public CarbonImmutable $due,
        public int $expiration,
        public array $tags,
    ) {}

    /**
     * @param  array<array-key, mixed>  $item
     */
    public static function fromWire(array $item): self
    {
        $due = $item['due'] ?? null;
        $tags = $item['tags'] ?? [];

        return new self(
            amount: (int) ($item['amount'] ?? 0),
            name: (string) ($item['name'] ?? ''),
            taxId: (string) ($item['taxId'] ?? ''),
            due: is_string($due) && $due !== ''
                ? CarbonImmutable::parse($due)
                : CarbonImmutable::now()->addDays(2),
            expiration: (int) ($item['expiration'] ?? 0),
            tags: WireTags::fromArray(is_array($tags) ? $tags : [])->toArray(),
        );
    }
}
