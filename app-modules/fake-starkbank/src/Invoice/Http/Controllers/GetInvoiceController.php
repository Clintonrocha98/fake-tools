<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Http\Controllers;

use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use He4rt\FakeStarkbank\Invoice\Actions\GetInvoice;
use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceView;
use He4rt\FakeStarkbank\Invoice\Exceptions\InvoiceNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * `GET /v2/invoice/{id}` — envelope singular `{"invoice": {...}}` no shape
 * magro da releitura. A leitura é o que faz o tempo passar: o avanço lazy roda
 * dentro da Action antes de a resposta ser montada.
 */
final readonly class GetInvoiceController
{
    public function __construct(
        private GetInvoice $getInvoice,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        try {
            $invoice = $this->getInvoice->handle($id);
        } catch (InvoiceNotFoundException) {
            return $this->errors->make(StarkbankErrorCode::InvalidId);
        }

        return response()->json(['invoice' => InvoiceView::fromModel($invoice)]);
    }
}
