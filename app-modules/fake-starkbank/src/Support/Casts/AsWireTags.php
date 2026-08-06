<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Support\Casts;

use He4rt\FakeStarkbank\Support\WireTags;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Ponte jsonb ↔ {@see WireTags}. O setter aceita o array cru além do VO para
 * que a atribuição vinda direto da wire (`tags` do body) continue sendo um
 * caminho tipado, e não um cast implícito escondido.
 *
 * @implements CastsAttributes<WireTags, WireTags|array<array-key, mixed>>
 */
final class AsWireTags implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): WireTags
    {
        $decoded = json_decode((string) ($value ?? '[]'), associative: true);

        return WireTags::fromArray(is_array($decoded) ? $decoded : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $tags = match (true) {
            $value instanceof WireTags => $value,
            is_array($value) => WireTags::fromArray($value),
            default => WireTags::empty(),
        };

        return json_encode($tags->toArray(), JSON_THROW_ON_ERROR);
    }
}
