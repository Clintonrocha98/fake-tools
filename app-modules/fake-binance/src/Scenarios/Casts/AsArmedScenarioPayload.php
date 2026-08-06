<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Casts;

use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<ArmedScenarioPayload, ArmedScenarioPayload|array<array-key, mixed>>
 */
final class AsArmedScenarioPayload implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ArmedScenarioPayload
    {
        $payload = json_decode((string) ($value ?? '{}'), associative: true);

        return ArmedScenarioPayload::fromArray(is_array($payload) ? $payload : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $payload = match (true) {
            $value instanceof ArmedScenarioPayload => $value,
            is_array($value) => ArmedScenarioPayload::fromArray($value),
            default => ArmedScenarioPayload::empty(),
        };

        return json_encode($payload->toArray(), JSON_THROW_ON_ERROR);
    }
}
