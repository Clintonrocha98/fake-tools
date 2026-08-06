<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Http\Controllers;

use He4rt\FakeStarkbank\Http\Errors\ErrorResponseFactory;
use He4rt\FakeStarkbank\Http\Errors\StarkbankErrorCode;
use He4rt\FakeStarkbank\Transfer\Actions\GetTransfer;
use He4rt\FakeStarkbank\Transfer\DTOs\TransferView;
use He4rt\FakeStarkbank\Transfer\Exceptions\TransferNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * `GET /v2/transfer/{id}` — envelope singular `{"transfer": {...}}`. A leitura
 * é o que faz o tempo passar: o avanço lazy roda dentro da Action antes de a
 * resposta ser montada.
 */
final readonly class GetTransferController
{
    public function __construct(
        private GetTransfer $getTransfer,
        private ErrorResponseFactory $errors,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        try {
            $transfer = $this->getTransfer->handle($id);
        } catch (TransferNotFoundException) {
            return $this->errors->make(StarkbankErrorCode::InvalidId);
        }

        return response()->json(['transfer' => TransferView::fromModel($transfer)]);
    }
}
