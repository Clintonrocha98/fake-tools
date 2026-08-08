<?php

declare(strict_types=1);

namespace He4rt\Control\Commands\Http\Controllers\Starkbank;

use He4rt\Control\Commands\Http\Requests\ForceStatusRequest;
use He4rt\Control\Http\WireEnum;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Invoice\Actions\ForceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use Illuminate\Http\JsonResponse;

/**
 * `POST /control/starkbank/invoices/{invoice}/force` — a mesma Action que a
 * ação `forceStatus` da tabela do painel invoca.
 */
final readonly class ForceInvoiceStatusController
{
    public function __construct(private ForceInvoiceStatus $force) {}

    public function __invoke(ForceStatusRequest $request, Invoice $invoice): JsonResponse
    {
        $status = WireEnum::resolve(InvoiceStatus::class, $request->status());

        return response()->json([
            'invoice' => ResourceRows::invoice($this->force->handle($invoice, $status))->toArray(),
        ]);
    }
}
