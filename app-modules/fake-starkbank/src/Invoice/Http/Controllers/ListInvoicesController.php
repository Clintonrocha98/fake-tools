<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Http\Controllers;

use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use He4rt\FakeStarkbank\Invoice\Actions\ListInvoices;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /v2/invoice?status=paid&after={cursor}` — o extrato de cash-in.
 *
 * O cursor viaja em `after` (fato observado nas Request classes do consumidor,
 * que vence a doc oficial); `status` é filtro de verdade contra todo o
 * vocabulário, não só o `paid` que o consumidor usa hoje.
 */
final readonly class ListInvoicesController
{
    public function __construct(
        private ListInvoices $listInvoices,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requested = $request->query('status');
        $status = null;

        if (is_string($requested) && $requested !== '') {
            $status = InvoiceStatus::tryFrom($requested);

            if (!$status instanceof InvoiceStatus) {
                StarkbankLog::warning('fake-starkbank.invoice: filtro de status fora do vocabulário — recusado em vez de servir lista vazia, que passaria por extrato sem movimento', [
                    'status' => $requested,
                ]);

                return $this->errors->make(
                    StarkbankErrorCode::InvalidRequest,
                    sprintf('Invalid invoice status: %s', $requested),
                );
            }
        }

        $after = $request->query('after');

        return response()->json(
            $this->listInvoices->handle($status, is_string($after) && $after !== '' ? $after : null),
        );
    }
}
