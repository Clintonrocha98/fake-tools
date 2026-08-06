<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\DTOs;

use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Support\WireTimestamp;
use JsonSerializable;

/**
 * O shape de LEITURA de uma invoice — `GET /v2/invoice/{id}` e cada item de
 * `GET /v2/invoice?status=...`, no camelCase da wire.
 *
 * Deliberadamente mais magro que o eco do POST ({@see IssuedInvoiceView}): a
 * releitura do StarkBank não devolve `nominalAmount`, `fee`, `fine`,
 * `interest`, `discounts`, `rules`, `splits`, `transactionIds`, `link` nem
 * `pdf`. Servir aqui o shape gordo do POST faria o fake aceitar um consumidor
 * que o StarkBank real quebraria.
 */
final readonly class InvoiceView implements JsonSerializable
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $name,
        public string $taxId,
        public string $status,
        public string $brcode,
        public InvoiceTags $tags,
        public string $created,
        public string $due,
        public int $expiration,
        public string $updated,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        return new self(
            id: $invoice->id,
            amount: $invoice->amount,
            name: $invoice->name,
            taxId: $invoice->tax_id,
            status: $invoice->status->value,
            brcode: $invoice->brcode,
            tags: $invoice->tags,
            created: WireTimestamp::format($invoice->created_at),
            due: WireTimestamp::format($invoice->due),
            expiration: $invoice->expiration,
            updated: WireTimestamp::format($invoice->updated_at),
        );
    }

    /**
     * @return array{id: string, amount: int, name: string, taxId: string, status: string, brcode: string, tags: list<string>, created: string, due: string, expiration: int, updated: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'name' => $this->name,
            'taxId' => $this->taxId,
            'status' => $this->status,
            'brcode' => $this->brcode,
            'tags' => $this->tags->toArray(),
            'created' => $this->created,
            'due' => $this->due,
            'expiration' => $this->expiration,
            'updated' => $this->updated,
        ];
    }
}
