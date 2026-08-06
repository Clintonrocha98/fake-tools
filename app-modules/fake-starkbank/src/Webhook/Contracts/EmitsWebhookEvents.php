<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Contracts;

/**
 * Contrato que as transições de estado (invoice, transfer, brcode-payment)
 * chamam para emitir eventos de webhook sem conhecer a implementação real de
 * assinatura e entrega.
 */
interface EmitsWebhookEvents
{
    /**
     * @param  array<string, mixed>  $entityPayload
     */
    public function emit(string $subscription, string $logType, array $entityPayload): void;
}
