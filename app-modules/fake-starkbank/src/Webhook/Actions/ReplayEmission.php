<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

/**
 * Reenvia uma emissão já gravada: MESMOS bytes, MESMA assinatura, mesmo
 * `event.id`. É assim que o replay exercita a idempotência do consumidor —
 * `firstOrCreate(event_id)` do lado de lá precisa reconhecer a repetição em vez
 * de conciliar duas vezes.
 *
 * Síncrono de propósito: quem dispara é o operador (painel ou console), não o
 * consumidor no meio de um request dele — o deadlock que
 * {@see EmitWebhookEvent} evita não existe aqui, e o operador quer ver o
 * resultado na hora.
 */
final readonly class ReplayEmission
{
    public function __construct(
        private DeliverEmission $deliver = new DeliverEmission,
    ) {}

    public function __invoke(WebhookEmission $emission): WebhookEmission
    {
        StarkbankLog::info('fake-starkbank.webhook: replay sob comando — reenviando os bytes gravados sem remontar o envelope, para o consumidor ver o mesmo event.id de novo', [
            'event_id' => $emission->event_id,
            'subscription' => $emission->subscription->value,
            'event_type' => $emission->event_type->value,
            'entity_id' => $emission->entity_id,
            'ja_entregue' => $emission->wasDelivered(),
        ]);

        $this->deliver->handle($emission);

        return $emission;
    }
}
