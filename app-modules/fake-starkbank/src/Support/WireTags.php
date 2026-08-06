<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Support;

use JsonSerializable;

/**
 * As `tags` de um recurso do StarkBank — invoice, transfer ou brcode-payment.
 * Lista de strings na wire, mas nunca um array solto aqui: `tags[0]` é o
 * correlationId (o id do Deposit ou do Payout do consumidor) e é por ele que
 * as varreduras de extrato deduplicam — ler essa posição por índice cru
 * espalha a convenção por todo call site.
 *
 * A convenção é a MESMA nas três pernas, por isso o VO mora no Support do
 * módulo: uma cópia por subsistema seria a mesma regra em três lugares para
 * divergir.
 *
 * A ordem é significativa e preservada: quem lê a posição 0 espera a primeira
 * tag que o consumidor enviou.
 */
final readonly class WireTags implements JsonSerializable
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
     * O correlationId que o consumidor carimbou na emissão — `null` quando o
     * recurso nasceu sem tags (o extrato de lá simplesmente o ignora).
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
