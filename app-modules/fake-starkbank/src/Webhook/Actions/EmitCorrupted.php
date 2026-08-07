<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Actions;

use He4rt\FakeStarkbank\Http\Auth\ThrowawayPrivateKey;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

/**
 * Emite um envelope bem formado com uma assinatura que NÃO fecha: mesma
 * montagem, mesma persistência, mesma entrega — só a chave é outra, gerada na
 * hora e jogada fora em seguida. A chave privada real do fake nunca entra neste
 * caminho, então o cenário não tem como produzir por acidente uma assinatura
 * válida.
 *
 * É o cenário adverso do contrato: o consumidor recusa com 401 e nada persiste
 * do lado dele. Sem ele, a verificação de `Digital-Signature` do monolito nunca
 * é exercitada em dev — só o caminho feliz seria.
 */
final readonly class EmitCorrupted
{
    public function __construct(
        private EmitWebhookEvent $emit = new EmitWebhookEvent,
        private ThrowawayPrivateKey $throwawayKey = new ThrowawayPrivateKey,
    ) {}

    /**
     * @param  array<string, mixed>  $entity
     */
    public function __invoke(
        StarkbankSubscription $subscription,
        StarkbankEventType $eventType,
        array $entity,
    ): WebhookEmission {
        StarkbankLog::warning('fake-starkbank.webhook: emissão CORROMPIDA sob comando — assinada com um par gerado na hora, o consumidor deve recusar com 401 e não persistir nada', [
            'subscription' => $subscription->value,
            'event_type' => $eventType->value,
            'entity_id' => $entity['id'] ?? null,
        ]);

        return $this->emit->handle($subscription, $eventType, $entity, $this->throwawayKey->pem());
    }
}
