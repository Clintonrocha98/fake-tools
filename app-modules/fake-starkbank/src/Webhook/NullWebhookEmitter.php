<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook;

use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use Illuminate\Support\Facades\Log;

/**
 * Binding default enquanto a emissão assinada real não existe: nenhum POST sai
 * para o consumidor. Loga a supressão para o dev não caçar por que o webhook
 * não chegou.
 */
final class NullWebhookEmitter implements EmitsWebhookEvents
{
    /**
     * @param  array<string, mixed>  $entityPayload
     */
    public function emit(string $subscription, string $logType, array $entityPayload): void
    {
        Log::debug('fake-starkbank.webhook: emissão suprimida — emissor nulo em uso, nenhum POST sai para o consumidor', [
            'subscription' => $subscription,
            'log_type' => $logType,
            'entity_id' => $entityPayload['id'] ?? null,
        ]);
    }
}
