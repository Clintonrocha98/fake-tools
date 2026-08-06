<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Casts;

use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceTags;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Ponte jsonb ↔ {@see InvoiceTags}. O setter aceita o array cru além do VO
 * para que a atribuição vinda direto da wire (`tags` do body) continue sendo um
 * caminho tipado, e não um cast implícito escondido.
 *
 * @implements CastsAttributes<InvoiceTags, InvoiceTags|array<array-key, mixed>>
 */
final class AsInvoiceTags implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): InvoiceTags
    {
        $decoded = json_decode((string) ($value ?? '[]'), associative: true);

        return InvoiceTags::fromArray(is_array($decoded) ? $decoded : []);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $tags = match (true) {
            $value instanceof InvoiceTags => $value,
            is_array($value) => InvoiceTags::fromArray($value),
            default => InvoiceTags::empty(),
        };

        return json_encode($tags->toArray(), JSON_THROW_ON_ERROR);
    }
}
