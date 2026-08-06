<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Support\WireTags;

/*
 * `tags[0]` é o correlationId (o id do Deposit ou do Payout do consumidor) — a
 * convenção que as varreduras de extrato usam para deduplicar. Ela vale igual
 * nas três pernas (invoice, transfer, brcode-payment) e vive aqui, não em cada
 * call site.
 */

it('devolve a primeira tag como correlationId', function (): void {
    expect(WireTags::fromArray(['deposit-abc-123', 'outra'])->correlationId())->toBe('deposit-abc-123');
});

it('não inventa correlationId quando o recurso nasceu sem tags', function (): void {
    expect(WireTags::empty()->correlationId())->toBeNull()
        ->and(WireTags::fromArray([])->correlationId())->toBeNull()
        ->and(WireTags::fromArray([''])->correlationId())->toBeNull();
});

it('preserva a ordem, porque a posição é o contrato', function (): void {
    expect(WireTags::fromArray(['a', 'b', 'c'])->toArray())->toBe(['a', 'b', 'c']);
});

it('normaliza para lista de strings e descarta o que não é escalar', function (): void {
    expect(WireTags::fromArray(['deposit-1', 42, ['aninhado'], null, true])->toArray())
        ->toBe(['deposit-1', '42', '1']);
});

it('serializa como lista JSON, nunca como objeto', function (): void {
    expect(json_encode(WireTags::fromArray(['a', 'b'])))->toBe('["a","b"]');
});
