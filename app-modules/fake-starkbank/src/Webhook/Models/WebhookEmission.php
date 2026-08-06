<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Models;

use App\Models\BaseModel;
use He4rt\FakeStarkbank\Database\Factories\Webhook\WebhookEmissionFactory;
use He4rt\FakeStarkbank\Webhook\Casts\AsWebhookPayload;
use He4rt\FakeStarkbank\Webhook\DTOs\WebhookPayload;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Uma emissão de webhook: o envelope montado, os bytes assinados e o resultado
 * da entrega. A linha nasce ANTES do POST — sem destino configurado ela existe
 * só para inspeção, e o `sent_at` nulo é o que o flush varre.
 *
 * @property string $id
 * @property string $event_id
 * @property StarkbankSubscription $subscription
 * @property StarkbankEventType $event_type
 * @property string $entity_id
 * @property string $url
 * @property WebhookPayload $payload
 * @property string $signature
 * @property int|null $response_code
 * @property Carbon|null $sent_at
 * @property string|null $failed_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<WebhookEmissionFactory>
 */
#[UseFactory(factoryClass: WebhookEmissionFactory::class)]
#[Table(name: 'fake_starkbank_webhook_emissions')]
final class WebhookEmission extends BaseModel
{
    public function wasDelivered(): bool
    {
        return $this->sent_at instanceof Carbon;
    }

    /**
     * Pendentes de entrega: nunca POSTadas com sucesso. Sem retry automático no
     * fake — quem as reenvia é `fake-starkbank:flush-webhooks` ou o replay
     * manual.
     *
     * @param  Builder<WebhookEmission>  $query
     */
    protected function scopePending(Builder $query): void
    {
        $query->whereNull('sent_at')->oldest();
    }

    protected function casts(): array
    {
        return [
            'subscription' => StarkbankSubscription::class,
            'event_type' => StarkbankEventType::class,
            'payload' => AsWebhookPayload::class,
            'response_code' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
