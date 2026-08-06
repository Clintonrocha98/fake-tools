<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook;

use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use Illuminate\Support\Facades\Log;

/**
 * O opt-out explícito da emissão: nenhum POST sai, nada é gravado. Não é mais o
 * binding default — quem responde pelo contrato é
 * {@see Actions\EmitWebhookEvent} —, mas continua
 * sendo o jeito de um teste (ou um ambiente) desligar a emissão sem mexer na
 * config de webhook. Loga a supressão para o dev não caçar por que o webhook
 * não chegou.
 */
final class NullWebhookEmitter implements EmitsWebhookEvents
{
    /**
     * @param  array<string, mixed>  $entityPayload
     */
    public function emit(string $subscription, string $logType, array $entityPayload, ?string $reason = null): void
    {
        Log::debug('fake-starkbank.webhook: emissão suprimida — emissor nulo vinculado no lugar do real, nenhum POST sai para o consumidor', [
            'subscription' => $subscription,
            'log_type' => $logType,
            'entity_id' => $entityPayload['id'] ?? null,
            'reason' => $reason,
        ]);
    }
}
