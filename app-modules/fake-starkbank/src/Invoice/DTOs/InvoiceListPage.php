<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\DTOs;

use JsonSerializable;

/**
 * Uma página de `GET /v2/invoice` — os itens no shape de leitura e o `cursor`
 * da próxima página. `cursor: null` é o sinal de fim que o consumidor espera
 * para parar de paginar.
 */
final readonly class InvoiceListPage implements JsonSerializable
{
    /**
     * @param  list<InvoiceView>  $invoices
     */
    public function __construct(
        public array $invoices,
        public ?string $cursor,
    ) {}

    /**
     * @return array{invoices: list<InvoiceView>, cursor: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'invoices' => $this->invoices,
            'cursor' => $this->cursor,
        ];
    }
}
