<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Actions;

use He4rt\FakeStarkbank\Invoice\Exceptions\InvoiceNotFoundException;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Support\StarkbankLog;

/**
 * `GET /v2/invoice/{id}` — a releitura autoritativa ("webhook = trigger,
 * GET = truth"). É ela que faz o tempo passar: o avanço lazy roda ANTES de
 * responder, então o estado que o consumidor lê aqui é o mesmo que a varredura
 * do extrato veria no mesmo instante.
 */
final readonly class GetInvoice
{
    public function __construct(private AdvanceInvoiceStatus $advance) {}

    public function handle(string $id): Invoice
    {
        $invoice = Invoice::query()->whereKey($id)->first();

        if (!$invoice instanceof Invoice) {
            StarkbankLog::warning('fake-starkbank.invoice: releitura de id que este fake nunca emitiu — 404 em vez de invoice vazia, que o consumidor leria como brcode em branco', [
                'invoice_id' => $id,
            ]);

            throw InvoiceNotFoundException::forId($id);
        }

        return $this->advance->handle($invoice);
    }
}
