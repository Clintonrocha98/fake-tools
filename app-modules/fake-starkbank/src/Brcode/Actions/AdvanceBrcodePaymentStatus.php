<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Actions;

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Brcode\DTOs\BrcodePaymentView;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Avanço automático LAZY do ciclo de vida do pagamento de BR Code: só roda
 * quando o pagamento é LIDO — `GET /v2/brcode-payment/{id}` ou a listagem —,
 * nunca por scheduler.
 *
 * Dois patamares sobre a idade desde a criação, no padrão do fake-binance:
 * 1×`fake-starkbank-brcode.advance_seconds` leva a `processing`, 2× leva a
 * `success`. Uma leitura muito posterior salta direto para `success` sem
 * persistir `processing` — o estado intermediário que ninguém observou não muda
 * nada do lado do consumidor, que relê por GET.
 *
 * `failed` nunca é alcançado pelo relógio: é desfecho de cenário
 * ({@see ForceBrcodePaymentStatus}) ou o destino que
 * {@see PlanNextBrcodePayment} gravou na criação. Um pagamento retido (`held`)
 * sobe até `processing` e não passa daí.
 */
final readonly class AdvanceBrcodePaymentStatus
{
    public function __construct(private EmitsWebhookEvents $webhooks) {}

    public function handle(BrcodePayment $payment): BrcodePayment
    {
        if (!$payment->status->advancesAutomatically()) {
            return $payment;
        }

        // A checagem fora do lock é o caminho comum de uma varredura de extrato:
        // sem ela, cada linha lida abriria uma transação e um SELECT FOR UPDATE
        // só para descobrir que nada mudou.
        if (!$this->targetStatus($payment) instanceof BrcodePaymentStatus) {
            return $payment;
        }

        $advanced = DB::transaction(function () use ($payment): ?BrcodePayment {
            // Duas leituras concorrentes disputam o mesmo avanço; o lock decide
            // quem transita, e quem perde relê o estado já avançado e não
            // reemite o evento.
            $locked = BrcodePayment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if (!$locked instanceof BrcodePayment || !$locked->status->advancesAutomatically()) {
                return null;
            }

            $target = $this->targetStatus($locked);

            if (!$target instanceof BrcodePaymentStatus) {
                return null;
            }

            // `destined_status` é zerado na transição: o destino de cenário
            // vale uma vez e não pode reaplicar-se a cada leitura.
            $locked->update(['status' => $target, 'destined_status' => null]);

            return $locked->refresh();
        });

        if (!$advanced instanceof BrcodePayment) {
            return $payment;
        }

        StarkbankLog::info('fake-starkbank.brcode: status avançado na leitura — o fake não tem scheduler, então é o próprio GET do consumidor que faz o tempo passar', [
            'payment_id' => $advanced->id,
            'from' => $payment->status->value,
            'to' => $advanced->status->value,
            'correlation_id' => $advanced->tags->correlationId(),
        ]);

        $eventType = $advanced->status->eventType();

        if ($eventType instanceof StarkbankEventType) {
            $this->webhooks->emit(
                StarkbankSubscription::BrcodePayment->value,
                $eventType->value,
                BrcodePaymentView::fromModel($advanced)->jsonSerialize(),
                $advanced->failure_reason,
            );
        }

        return $advanced;
    }

    private function targetStatus(BrcodePayment $payment): ?BrcodePaymentStatus
    {
        $destined = $payment->destined_status;

        // O destino gravado por cenário vence o relógio e é aplicado na primeira
        // leitura — é o único caminho até `failed` sem clique de operador.
        if ($destined instanceof BrcodePaymentStatus) {
            return $destined === $payment->status ? null : $destined;
        }

        $advanceSeconds = $this->advanceSeconds();

        if ($advanceSeconds <= 0) {
            return null;
        }

        $age = $payment->created_at?->diffInSeconds(CarbonImmutable::now()) ?? 0.0;

        // Retido por cenário: chega a `processing` e para ali. Sem esta saída
        // antecipada, uma leitura tardia saltaria direto para `success` e a
        // retenção não teria acontecido.
        if ($payment->held) {
            return $payment->status === BrcodePaymentStatus::Created && $age >= $advanceSeconds
                ? BrcodePaymentStatus::Processing
                : null;
        }

        if ($age >= $advanceSeconds * 2) {
            return BrcodePaymentStatus::Success;
        }

        if ($age >= $advanceSeconds && $payment->status === BrcodePaymentStatus::Created) {
            return BrcodePaymentStatus::Processing;
        }

        return null;
    }

    /**
     * `config()->integer()` exigiria um int estrito e explodiria na
     * numeric-string que `env()` produz de um `.env` real.
     */
    private function advanceSeconds(): int
    {
        return (int) config('fake-starkbank-brcode.advance_seconds', 60);
    }
}
