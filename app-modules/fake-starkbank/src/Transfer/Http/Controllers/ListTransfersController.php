<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Http\Controllers;

use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Transfer\Actions\ListTransfers;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /v2/transfer?status=success&after={cursor}` — o extrato de cash-out.
 *
 * O cursor viaja em `after` (fato observado nas Request classes do consumidor,
 * que vence a doc oficial); `status` é filtro de verdade contra todo o
 * vocabulário, não só o `success` que o consumidor usa hoje.
 */
final readonly class ListTransfersController
{
    public function __construct(
        private ListTransfers $listTransfers,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requested = $request->query('status');
        $status = null;

        if (is_string($requested) && $requested !== '') {
            $status = TransferStatus::tryFrom($requested);

            if (!$status instanceof TransferStatus) {
                StarkbankLog::warning('fake-starkbank.transfer: filtro de status fora do vocabulário — recusado em vez de servir lista vazia, que passaria por extrato sem movimento', [
                    'status' => $requested,
                ]);

                return $this->errors->make(
                    StarkbankErrorCode::InvalidRequest,
                    sprintf('Invalid transfer status: %s', $requested),
                );
            }
        }

        $after = $request->query('after');

        return response()->json(
            $this->listTransfers->handle($status, is_string($after) && $after !== '' ? $after : null),
        );
    }
}
