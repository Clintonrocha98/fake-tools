<?php

declare(strict_types=1);

namespace He4rt\Control\Feed\Casts;

use He4rt\Control\Feed\DTOs\ControlEventContext;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<ControlEventContext, ControlEventContext|array<array-key, mixed>>
 */
final class AsControlEventContext implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ControlEventContext
    {
        $context = json_decode((string) ($value ?? '{}'), associative: true);

        return ControlEventContext::fromArray(is_array($context) ? $context : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $context = match (true) {
            $value instanceof ControlEventContext => $value,
            is_array($value) => ControlEventContext::fromArray($value),
            default => ControlEventContext::empty(),
        };

        return json_encode($context->toArray(), JSON_THROW_ON_ERROR);
    }
}
