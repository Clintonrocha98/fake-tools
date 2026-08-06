<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\DTOs;

use JsonSerializable;

/**
 * As `tags` de uma invoice. Lista de strings na wire, mas nunca um array solto
 * aqui: `tags[0]` é o correlationId (o id do Deposit do consumidor) e é por ele
 * que `PollExtratoCommand` deduplica o extrato — ler essa posição por índice
 * cru espalha a convenção por todo call site.
 *
 * A ordem é significativa e preservada: quem lê a posição 0 espera a primeira
 * tag que o consumidor enviou.
 */
final readonly class InvoiceTags implements JsonSerializable
{
    /**
     * @param  list<string>  $values
     */
    private function __construct(public array $values) {}

    /**
     * @param  array<array-key, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $tags = [];

        foreach ($values as $value) {
            if (is_scalar($value)) {
                $tags[] = (string) $value;
            }
        }

        return new self($tags);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * O correlationId que o consumidor carimbou na emissão — `null` quando a
     * invoice nasceu sem tags (o extrato de lá simplesmente a ignora).
     */
    public function correlationId(): ?string
    {
        $first = $this->values[0] ?? null;

        return $first === null || $first === '' ? null : $first;
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /**
     * @return list<string>
     */
    public function jsonSerialize(): array
    {
        return $this->values;
    }
}
