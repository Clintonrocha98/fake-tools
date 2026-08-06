<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Http\Controllers;

use He4rt\FakeStarkbank\Transfer\Actions\SendTransfer;
use He4rt\FakeStarkbank\Transfer\DTOs\SendTransferData;
use He4rt\FakeStarkbank\Transfer\DTOs\TransferView;
use He4rt\FakeStarkbank\Transfer\Http\Requests\SendTransferRequest;
use Illuminate\Http\JsonResponse;

/**
 * `POST /v2/transfer` — a assinatura é verificada pelo middleware
 * `fake-starkbank.signed` na definição da rota, nunca aqui.
 *
 * A resposta mantém a ordem do array recebido: o consumidor lê `transfers.0`
 * posicionalmente e trata `transfers` vazio ou sem `id` como
 * `StarkbankRequestFailed::malformed()`.
 */
final readonly class SendTransferController
{
    public function __construct(private SendTransfer $sendTransfer) {}

    public function __invoke(SendTransferRequest $request): JsonResponse
    {
        /** @var array<int, array<array-key, mixed>> $items */
        $items = $request->validated('transfers');

        $transfers = [];

        foreach ($items as $item) {
            $transfers[] = TransferView::fromModel(
                $this->sendTransfer->handle(SendTransferData::fromWire($item)),
            );
        }

        return response()->json(['transfers' => $transfers]);
    }
}
