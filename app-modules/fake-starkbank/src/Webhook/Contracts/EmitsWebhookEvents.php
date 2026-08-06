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
     * @param  string|null  $reason  Motivo textual do desfecho, quando existe — viaja
     *                               no `event.log` para o operador ler do lado do consumidor
     */
    public function emit(string $subscription, string $logType, array $entityPayload, ?string $reason = null): void;
}
