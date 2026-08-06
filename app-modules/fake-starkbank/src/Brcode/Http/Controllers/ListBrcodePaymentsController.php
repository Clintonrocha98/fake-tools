<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Http\Controllers;

use He4rt\FakeStarkbank\Brcode\Actions\ListBrcodePayments;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * `GET /v2/brcode-payment?status=success&after={cursor}` — o extrato de
 * funding.
 *
 * O cursor viaja em `after` (fato observado nas Request classes do consumidor,
 * que vence a doc oficial); `status` é filtro de verdade contra todo o
 * vocabulário, não só o `success` que o consumidor usa hoje.
 */
final readonly class ListBrcodePaymentsController
{
    public function __construct(
        private ListBrcodePayments $listBrcodePayments,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $requested = $request->query('status');
        $status = null;

        if (is_string($requested) && $requested !== '') {
            $status = BrcodePaymentStatus::tryFrom($requested);

            if (!$status instanceof BrcodePaymentStatus) {
                Log::warning('fake-starkbank.brcode: filtro de status fora do vocabulário — recusado em vez de servir lista vazia, que passaria por extrato sem movimento', [
                    'status' => $requested,
                ]);

                return $this->errors->make(
                    StarkbankErrorCode::InvalidRequest,
                    sprintf('Invalid brcode payment status: %s', $requested),
                );
            }
        }

        $after = $request->query('after');

        return response()->json(
            $this->listBrcodePayments->handle($status, is_string($after) && $after !== '' ? $after : null),
        );
    }
}
