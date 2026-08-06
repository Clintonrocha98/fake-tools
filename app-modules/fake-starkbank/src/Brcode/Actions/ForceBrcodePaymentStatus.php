<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Actions;

use He4rt\FakeStarkbank\Brcode\DTOs\BrcodePaymentView;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Cenário: move o pagamento para qualquer estado do vocabulário IGNORANDO o
 * relógio, e emite o evento correspondente quando ele existe.
 *
 * É o único caminho até `failed` — nenhum avanço temporal o produz —, e é o
 * ponto de extensão que o painel de cenários vai pendurar num botão. O status
 * forçado é GRAVADO: os dois desfechos são terminais para o avanço lazy, então
 * nenhuma leitura seguinte os desfaz.
 */
final readonly class ForceBrcodePaymentStatus
{
    public function __construct(private EmitsWebhookEvents $webhooks) {}

    public function handle(BrcodePayment $payment, BrcodePaymentStatus $status): BrcodePayment
    {
        $from = $payment->status;

        $payment->update(['status' => $status]);
        $payment->refresh();

        Log::info('fake-starkbank.brcode: status forçado por cenário — o relógio é ignorado de propósito, para exercitar a recusa de funding que o consumidor trata mas o relógio nunca produz', [
            'payment_id' => $payment->id,
            'from' => $from->value,
            'to' => $status->value,
            'correlation_id' => $payment->tags->correlationId(),
        ]);

        $eventType = $status->eventType();

        if ($eventType instanceof StarkbankEventType) {
            $this->webhooks->emit(
                StarkbankSubscription::BrcodePayment->value,
                $eventType->value,
                BrcodePaymentView::fromModel($payment)->jsonSerialize(),
            );
        }

        return $payment;
    }
}
