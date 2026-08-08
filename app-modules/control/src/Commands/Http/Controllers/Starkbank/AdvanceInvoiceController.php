<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Invoice\Actions\AdvanceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/invoices/{invoice}/advance` — roda o avanço lazy
 * agora, sem esperar o relógio nem depender de uma releitura do consumidor.
 *
 * O painel não oferece este gesto porque quem opera o painel tem tempo de
 * esperar; a sidebar do consumidor não tem, e a Action é a mesma que toda
 * leitura já dispara.
 */
final readonly class AdvanceInvoiceController
{
    public function __construct(private AdvanceInvoiceStatus $advance) {}

    public function __invoke(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'invoice' => ResourceRows::invoice($this->advance->handle($invoice))->toArray(),
        ]);
    }
}
