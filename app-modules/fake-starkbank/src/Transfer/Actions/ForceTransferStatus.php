<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Actions;

use He4rt\FakeStarkbank\Transfer\DTOs\TransferView;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Cenário: move a transfer para qualquer estado do vocabulário IGNORANDO o
 * relógio, e emite o evento correspondente quando ele existe.
 *
 * É o único caminho até `failed` e `returned` — nenhum avanço temporal os
 * produz —, e é o ponto de extensão que o painel de cenários vai pendurar num
 * botão. O status forçado é GRAVADO: os três desfechos são terminais para o
 * avanço lazy, então nenhuma leitura seguinte os desfaz.
 */
final readonly class ForceTransferStatus
{
    public function __construct(private EmitsWebhookEvents $webhooks) {}

    public function handle(Transfer $transfer, TransferStatus $status): Transfer
    {
        $from = $transfer->status;

        // O operador que força um estado descarta qualquer destino de cenário
        // ainda pendente — mantê-lo faria a próxima leitura desfazer o clique.
        $transfer->update(['status' => $status, 'destined_status' => null, 'held' => false]);
        $transfer->refresh();

        Log::info('fake-starkbank.transfer: status forçado por cenário — o relógio é ignorado de propósito, para exercitar o ramo de devolução que o consumidor trata mas raramente vê', [
            'transfer_id' => $transfer->id,
            'from' => $from->value,
            'to' => $status->value,
            'correlation_id' => $transfer->tags->correlationId(),
        ]);

        $eventType = $status->eventType();

        if ($eventType instanceof StarkbankEventType) {
            $this->webhooks->emit(
                StarkbankSubscription::Transfer->value,
                $eventType->value,
                TransferView::fromModel($transfer)->jsonSerialize(),
                $transfer->failure_reason,
            );
        }

        return $transfer;
    }
}
