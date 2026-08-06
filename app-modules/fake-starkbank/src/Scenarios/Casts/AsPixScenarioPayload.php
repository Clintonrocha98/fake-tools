<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Casts;

use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<PixScenarioPayload, PixScenarioPayload|array<array-key, mixed>>
 */
final class AsPixScenarioPayload implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): PixScenarioPayload
    {
        $payload = json_decode((string) ($value ?? '{}'), associative: true);

        return PixScenarioPayload::fromArray(is_array($payload) ? $payload : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $payload = match (true) {
            $value instanceof PixScenarioPayload => $value,
            is_array($value) => PixScenarioPayload::fromArray($value),
            default => PixScenarioPayload::empty(),
        };

        return json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
    }
}
