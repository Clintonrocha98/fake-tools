<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Dict\Enums\DictKeyType;
use He4rt\FakeStarkbank\Dict\Enums\DictOwnerType;
use He4rt\FakeStarkbank\Dict\Support\OpaqueAccountBlob;

/*
 * O vocabulário do registro DICT e o formato dos blobs opacos que ele devolve.
 */

it('fixa o valor de wire de cada tipo de chave', function (): void {
    expect(array_map(static fn (DictKeyType $type): string => $type->value, DictKeyType::cases()))
        ->toBe(['email', 'phone', 'document', 'random']);
});

it('mantém o ownerType em camelCase, como o fixture do consumidor', function (): void {
    expect(DictOwnerType::NaturalPerson->value)->toBe('naturalPerson')
        ->and(DictOwnerType::LegalEntity->value)->toBe('legalEntity');
});

it('gera blobs no formato opaco do provedor', function (): void {
    $branch = OpaqueAccountBlob::forBranch('ada@brd.digital');
    $account = OpaqueAccountBlob::forAccount('ada@brd.digital');

    expect($branch)->toMatch('#^\*[A-Za-z0-9+/]+=*$#')
        ->and($account)->toMatch('#^\*[A-Za-z0-9+/]+=*$#')
        ->and($branch)->not->toBe($account);
});

it('deriva o mesmo blob da mesma chave, para que reseed não mude o beneficiário', function (): void {
    expect(OpaqueAccountBlob::forBranch('ada@brd.digital'))
        ->toBe(OpaqueAccountBlob::forBranch('ada@brd.digital'))
        ->and(OpaqueAccountBlob::forBranch('ada@brd.digital'))
        ->not->toBe(OpaqueAccountBlob::forBranch('funding@fake-binance.dev'));
});
