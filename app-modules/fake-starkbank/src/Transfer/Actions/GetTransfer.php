<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Actions;

use He4rt\FakeStarkbank\Transfer\Exceptions\TransferNotFoundException;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use Illuminate\Support\Facades\Log;

/**
 * `GET /v2/transfer/{id}` — a releitura autoritativa ("webhook = trigger,
 * GET = truth"). É dela que `ConfirmTransferSettlement` monta o Settlement
 * Fact, e é ela que faz o tempo passar: o avanço lazy roda ANTES de responder,
 * então o estado lido aqui é o mesmo que a varredura do extrato veria no mesmo
 * instante.
 */
final readonly class GetTransfer
{
    public function __construct(private AdvanceTransferStatus $advance) {}

    public function handle(string $id): Transfer
    {
        $transfer = Transfer::query()->whereKey($id)->first();

        if (!$transfer instanceof Transfer) {
            Log::warning('fake-starkbank.transfer: releitura de id que este fake nunca despachou — 404 em vez de transfer vazia, que o consumidor leria como resposta malformada', [
                'transfer_id' => $id,
            ]);

            throw TransferNotFoundException::forId($id);
        }

        return $this->advance->handle($transfer);
    }
}
