<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Transfer\DTOs\TransferView;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Avanço automático LAZY do ciclo de vida da transfer: só roda quando a
 * transfer é LIDA — `GET /v2/transfer/{id}`, a listagem, ou a checagem de
 * idempotência de um `POST` repetido —, nunca por scheduler.
 *
 * Dois patamares sobre a idade desde a criação, no padrão do fake-binance:
 * 1×`fake-starkbank-transfer.advance_seconds` leva a `processing`, 2× leva a
 * `success`. Uma leitura muito posterior salta direto para `success` sem
 * persistir `processing` — o estado intermediário que ninguém observou não muda
 * nada do lado do consumidor, que relê por GET.
 *
 * `failed` e `returned` nunca são alcançados pelo relógio: são desfechos de
 * cenário ({@see ForceTransferStatus}) ou o destino que
 * {@see PlanNextTransfer} gravou na criação. Uma transfer retida (`held`) sobe
 * até `processing` e não passa daí.
 */
final readonly class AdvanceTransferStatus
{
    public function __construct(private EmitsWebhookEvents $webhooks) {}

    public function handle(Transfer $transfer): Transfer
    {
        if (!$transfer->status->advancesAutomatically()) {
            return $transfer;
        }

        // A checagem fora do lock é o caminho comum de uma varredura de extrato:
        // sem ela, cada linha lida abriria uma transação e um SELECT FOR UPDATE
        // só para descobrir que nada mudou.
        if (!$this->targetStatus($transfer) instanceof TransferStatus) {
            return $transfer;
        }

        $advanced = DB::transaction(function () use ($transfer): ?Transfer {
            // Duas leituras concorrentes disputam o mesmo avanço; o lock decide
            // quem transita, e quem perde relê o estado já avançado e não
            // reemite o evento.
            $locked = Transfer::query()->whereKey($transfer->getKey())->lockForUpdate()->first();

            if (!$locked instanceof Transfer || !$locked->status->advancesAutomatically()) {
                return null;
            }

            $target = $this->targetStatus($locked);

            if (!$target instanceof TransferStatus) {
                return null;
            }

            // `destined_status` é zerado na transição: o destino de cenário
            // vale uma vez e não pode reaplicar-se a cada leitura.
            $locked->update(['status' => $target, 'destined_status' => null]);

            return $locked->refresh();
        });

        if (!$advanced instanceof Transfer) {
            return $transfer;
        }

        StarkbankLog::info('fake-starkbank.transfer: status avançado na leitura — o fake não tem scheduler, então é o próprio GET do consumidor que faz o tempo passar', [
            'transfer_id' => $advanced->id,
            'from' => $transfer->status->value,
            'to' => $advanced->status->value,
            'correlation_id' => $advanced->tags->correlationId(),
        ]);

        $eventType = $advanced->status->eventType();

        if ($eventType instanceof StarkbankEventType) {
            $this->webhooks->emit(
                StarkbankSubscription::Transfer->value,
                $eventType->value,
                TransferView::fromModel($advanced)->jsonSerialize(),
                $advanced->failure_reason,
            );
        }

        return $advanced;
    }

    private function targetStatus(Transfer $transfer): ?TransferStatus
    {
        $destined = $transfer->destined_status;

        // O destino gravado por cenário vence o relógio e é aplicado na primeira
        // leitura — é o único caminho até `failed` sem clique de operador.
        if ($destined instanceof TransferStatus) {
            return $destined === $transfer->status ? null : $destined;
        }

        $advanceSeconds = $this->advanceSeconds();

        if ($advanceSeconds <= 0) {
            return null;
        }

        $age = $transfer->created_at?->diffInSeconds(CarbonImmutable::now()) ?? 0.0;

        // Retida por cenário: chega a `processing` e para ali. Sem esta saída
        // antecipada, uma leitura tardia saltaria direto para `success` e a
        // retenção não teria acontecido.
        if ($transfer->held) {
            return $transfer->status === TransferStatus::Created && $age >= $advanceSeconds
                ? TransferStatus::Processing
                : null;
        }

        if ($age >= $advanceSeconds * 2) {
            return TransferStatus::Success;
        }

        if ($age >= $advanceSeconds && $transfer->status === TransferStatus::Created) {
            return TransferStatus::Processing;
        }

        return null;
    }

    /**
     * `config()->integer()` exigiria um int estrito e explodiria na
     * numeric-string que `env()` produz de um `.env` real.
     */
    private function advanceSeconds(): int
    {
        return (int) config('fake-starkbank-transfer.advance_seconds', 60);
    }
}
