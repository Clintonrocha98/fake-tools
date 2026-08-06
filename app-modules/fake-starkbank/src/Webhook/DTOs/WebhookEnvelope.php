<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\DTOs;

use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use JsonSerializable;

/**
 * O envelope `{"event": {...}}` que o fake POSTa no
 * `POST /webhooks/starkbank` do consumidor, no vocabulário de wire do StarkBank
 * — camelCase (`workspaceId`), `created` em ISO-8601 com microssegundos e a
 * entity completa embutida sob a key da subscription
 * ({@see StarkbankSubscription::logKey()}).
 *
 * `event.id` é a chave de idempotência do consumidor: ele grava a entrega em
 * `firstOrCreate(event_id)`, então replay e duplicata precisam reusar o MESMO
 * valor — quem gera o id é a emissão, nunca a serialização.
 *
 * O consumidor só lê o `id` da entity ("webhook = trigger, GET = truth"), mas o
 * envelope carrega a entity inteira: fidelidade barata que não quebra se algum
 * consumidor futuro olhar mais campos.
 *
 * `logReason` é o motivo que um cenário de recusa armou. Viaja no log só quando
 * existe, e o consumidor o ignora — está ali para o operador ler no corpo da
 * entrega.
 */
final readonly class WebhookEnvelope implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $entity
     */
    public function __construct(
        public string $eventId,
        public string $created,
        public string $workspaceId,
        public StarkbankSubscription $subscription,
        public string $logId,
        public string $logCreated,
        public StarkbankEventType $logType,
        public array $entity,
        public ?string $logReason = null,
    ) {}

    public function entityId(): string
    {
        $id = $this->entity['id'] ?? null;

        return is_scalar($id) ? (string) $id : '';
    }

    /**
     * O `reason` só entra no log quando existe: o StarkBank não o emite nas
     * transições limpas, e uma key sempre presente com valor nulo mudaria o
     * shape que o consumidor tem gravado.
     *
     * @return array{event: array{id: string, created: string, workspaceId: string, subscription: string, log: array<string, mixed>}}
     */
    public function jsonSerialize(): array
    {
        $log = [
            'id' => $this->logId,
            'created' => $this->logCreated,
            'type' => $this->logType->value,
            $this->subscription->logKey() => $this->entity,
        ];

        if ($this->logReason !== null) {
            $log['reason'] = $this->logReason;
        }

        return [
            'event' => [
                'id' => $this->eventId,
                'created' => $this->created,
                'workspaceId' => $this->workspaceId,
                'subscription' => $this->subscription->value,
                'log' => $log,
            ],
        ];
    }
}
