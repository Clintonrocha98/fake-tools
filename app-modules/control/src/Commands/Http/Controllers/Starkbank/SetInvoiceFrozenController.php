<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\Commands\Http\Requests\SetFrozenRequest;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Invoice\Actions\SetInvoiceFrozen;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/invoices/{invoice}/freeze` — o painel usa um toggle;
 * aqui o valor é explícito, porque quem chama é uma máquina e um toggle sob
 * retry deixaria o estado dependendo de quantas vezes a chamada saiu.
 */
final readonly class SetInvoiceFrozenController
{
    public function __construct(private SetInvoiceFrozen $freeze) {}

    public function __invoke(SetFrozenRequest $request, Invoice $invoice): JsonResponse
    {
        return response()->json([
            'invoice' => ResourceRows::invoice($this->freeze->handle($invoice, $request->frozen()))->toArray(),
        ]);
    }
}
