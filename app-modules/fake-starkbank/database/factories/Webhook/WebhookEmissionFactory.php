<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Database\Factories\Webhook;

use He4rt\FakeStarkbank\Webhook\DTOs\WebhookPayload;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/** @extends Factory<WebhookEmission> */
class WebhookEmissionFactory extends Factory
{
    protected $model = WebhookEmission::class;

    public function definition(): array
    {
        $eventId = (string) fake()->numerify('################');
        $entityId = (string) fake()->numerify('################');

        return [
            'event_id' => $eventId,
            'subscription' => StarkbankSubscription::Invoice,
            'event_type' => StarkbankEventType::Paid,
            'entity_id' => $entityId,
            'url' => 'https://consumidor.test/webhooks/starkbank',
            'payload' => WebhookPayload::fromRawBody(sprintf('{"event":{"id":"%s"}}', $eventId)),
            'signature' => base64_encode('assinatura-de-fixture'),
            'response_code' => null,
            'sent_at' => null,
            'held_at' => null,
            'failed_reason' => null,
        ];
    }

    /**
     * Emissão já entregue — o flush precisa ignorá-la.
     */
    public function delivered(): static
    {
        return $this->state(fn (): array => [
            'response_code' => 200,
            'sent_at' => Date::now(),
            'failed_reason' => null,
        ]);
    }

    /**
     * Emissão que tentou sair e não saiu: pendente com motivo registrado.
     */
    public function failed(string $reason = 'Connection refused'): static
    {
        return $this->state(fn (): array => [
            'response_code' => null,
            'sent_at' => null,
            'failed_reason' => $reason,
        ]);
    }

    /**
     * Emissão represada pelo desfecho `HoldNext` — nunca foi POSTada e o flush
     * a ignora até a liberação manual.
     */
    public function held(): static
    {
        return $this->state(fn (): array => [
            'response_code' => null,
            'sent_at' => null,
            'held_at' => Date::now(),
        ]);
    }
}
