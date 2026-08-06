<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\DTOs;

use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use JsonSerializable;

/**
 * O eco COMPLETO de `POST /v2/invoice` — o shape gordo, com todos os campos
 * que o StarkBank devolve na emissão, inclusive os que o consumidor não lê.
 *
 * O fake não modela multa, juros, desconto, split, rule nem transação: esses
 * campos saem como as constantes do fixture, a mesma disciplina que o
 * fake-binance usa para o que está fora do destino. Modelá-los pela metade
 * seria pior que servir a constante — daria a impressão de que variam.
 */
final readonly class IssuedInvoiceView implements JsonSerializable
{
    private const string LINK_TEMPLATE = 'https://brdcapital.sandbox.starkbank.com/invoicelink/%s';

    private const string PDF_TEMPLATE = 'https://sandbox.api.starkbank.com/v2/invoice/%s.pdf';

    public function __construct(public InvoiceView $invoice) {}

    public static function fromModel(Invoice $invoice): self
    {
        return new self(InvoiceView::fromModel($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->invoice->id,
            'amount' => $this->invoice->amount,
            // Sem desconto vivo, o valor nominal é sempre o cobrado.
            'nominalAmount' => $this->invoice->amount,
            'name' => $this->invoice->name,
            'taxId' => $this->invoice->taxId,
            'status' => $this->invoice->status,
            'brcode' => $this->invoice->brcode,
            'tags' => $this->invoice->tags->toArray(),
            'created' => $this->invoice->created,
            'due' => $this->invoice->due,
            'expiration' => $this->invoice->expiration,
            'fee' => 0,
            'fine' => 2,
            'fineAmount' => 0,
            'interest' => 1,
            'interestAmount' => 0,
            'discountAmount' => 0,
            'discounts' => [],
            'descriptions' => [],
            'displayDescription' => '',
            'reversalDisplayDescription' => '',
            'rules' => [],
            'splits' => [],
            'metadata' => [],
            'transactionIds' => [],
            'link' => sprintf(self::LINK_TEMPLATE, $this->invoice->id),
            'pdf' => sprintf(self::PDF_TEMPLATE, $this->invoice->id),
            'updated' => $this->invoice->updated,
        ];
    }
}
