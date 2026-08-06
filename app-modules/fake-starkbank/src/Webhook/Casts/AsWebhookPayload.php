<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Casts;

use He4rt\FakeStarkbank\Webhook\DTOs\WebhookPayload;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<WebhookPayload, WebhookPayload|array<array-key, mixed>|string>
 */
final class AsWebhookPayload implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): WebhookPayload
    {
        $payload = json_decode((string) ($value ?? '{}'), associative: true);

        return WebhookPayload::fromArray(is_array($payload) ? $payload : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $payload = match (true) {
            $value instanceof WebhookPayload => $value,
            is_string($value) => WebhookPayload::fromRawBody($value),
            is_array($value) => WebhookPayload::fromArray($value),
            default => WebhookPayload::fromRawBody(''),
        };

        return json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
    }
}
