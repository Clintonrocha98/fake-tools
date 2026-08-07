<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

/**
 * Libera uma emissão represada pelo desfecho `HoldNext` e a entrega na hora,
 * com os MESMOS bytes e a MESMA assinatura que já estavam gravados — o envelope
 * nunca é remontado, então o `event.id` que o consumidor recebe é o do instante
 * em que o evento aconteceu, não o da liberação.
 *
 * Síncrono de propósito: quem dispara é o operador no painel, não o consumidor
 * no meio de um request dele. Liberar o que não estava represado é no-op — o
 * estado final é o mesmo, e um erro aqui só atrapalharia a UI.
 */
final readonly class ReleaseEmissionHold
{
    public function __construct(
        private DeliverEmission $deliver = new DeliverEmission,
    ) {}

    public function __invoke(WebhookEmission $emission): WebhookEmission
    {
        if (!$emission->isHeld()) {
            StarkbankLog::info('fake-starkbank.webhook: liberação ignorada — a emissão não estava represada, e reentregá-la aqui seria um replay disfarçado', [
                'event_id' => $emission->event_id,
            ]);

            return $emission;
        }

        $emission->forceFill(['held_at' => null])->save();

        StarkbankLog::info('fake-starkbank.webhook: emissão liberada sob comando — os bytes represados saem como estavam, com o event.id do instante do evento', [
            'event_id' => $emission->event_id,
            'subscription' => $emission->subscription->value,
            'event_type' => $emission->event_type->value,
            'entity_id' => $emission->entity_id,
        ]);

        $this->deliver->handle($emission);

        return $emission->refresh();
    }
}
