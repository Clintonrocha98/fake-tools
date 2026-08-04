<?php

declare(strict_types=1);

namespace He4rt\Identity\ExternalIdentity\Casts;

use He4rt\Identity\ExternalIdentity\Data\IdentityMetadata;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<IdentityMetadata, IdentityMetadata|array<array-key, mixed>>
 */
final class AsIdentityMetadata implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): IdentityMetadata
    {
        $payload = json_decode((string) ($value ?? '{}'), associative: true);

        return IdentityMetadata::fromArray(is_array($payload) ? $payload : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $metadata = match (true) {
            $value instanceof IdentityMetadata => $value,
            is_array($value) => IdentityMetadata::fromArray($value),
            default => IdentityMetadata::empty(),
        };

        return json_encode($metadata->toArray(), JSON_THROW_ON_ERROR);
    }
}
