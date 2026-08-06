<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Invoice\DTOs\InvoiceTags;

/*
 * `tags[0]` é o correlationId (o id do Deposit do consumidor) — a convenção que
 * `PollExtratoCommand` usa para deduplicar o extrato. Ela vive aqui, não em cada
 * call site.
 */

it('devolve a primeira tag como correlationId', function (): void {
    expect(InvoiceTags::fromArray(['deposit-abc-123', 'outra'])->correlationId())->toBe('deposit-abc-123');
});

it('não inventa correlationId quando a invoice nasceu sem tags', function (): void {
    expect(InvoiceTags::empty()->correlationId())->toBeNull()
        ->and(InvoiceTags::fromArray([])->correlationId())->toBeNull()
        ->and(InvoiceTags::fromArray([''])->correlationId())->toBeNull();
});

it('preserva a ordem, porque a posição é o contrato', function (): void {
    expect(InvoiceTags::fromArray(['a', 'b', 'c'])->toArray())->toBe(['a', 'b', 'c']);
});

it('normaliza para lista de strings e descarta o que não é escalar', function (): void {
    expect(InvoiceTags::fromArray(['deposit-1', 42, ['aninhado'], null, true])->toArray())
        ->toBe(['deposit-1', '42', '1']);
});

it('serializa como lista JSON, nunca como objeto', function (): void {
    expect(json_encode(InvoiceTags::fromArray(['a', 'b'])))->toBe('["a","b"]');
});
