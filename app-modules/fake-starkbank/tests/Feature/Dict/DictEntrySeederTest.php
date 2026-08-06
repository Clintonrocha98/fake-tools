<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;

/*
|--------------------------------------------------------------------------
| Seed do registro DICT
|--------------------------------------------------------------------------
|
| Duas chaves: o beneficiário de referência do fixture do consumidor e o
| recebedor do funding cross-fake. O entrypoint do container roda o seed em todo
| start, então rodar duas vezes precisa ser inofensivo.
|
*/

it('registra as duas chaves do contrato', function (): void {
    $this->seed(DictEntrySeeder::class);

    expect(DictEntry::query()->pluck('pix_key')->all())
        ->toEqualCanonicalizing(['ada@brd.digital', 'funding@fake-binance.dev']);
});

it('é idempotente: reseedar não duplica nem muda os blobs', function (): void {
    $this->seed(DictEntrySeeder::class);

    $antes = DictEntry::query()->where('pix_key', 'ada@brd.digital')->firstOrFail();

    $this->seed(DictEntrySeeder::class);

    $depois = DictEntry::query()->where('pix_key', 'ada@brd.digital')->firstOrFail();

    expect(DictEntry::query()->count())->toBe(2)
        ->and($depois->id)->toBe($antes->id)
        ->and($depois->branch_code_blob)->toBe($antes->branch_code_blob)
        ->and($depois->account_number_blob)->toBe($antes->account_number_blob);
});

it('reescreve a entry quando a constante cross-fake muda no env', function (): void {
    // Um registro DICT é estado DECLARATIVO: um guard de "já existe alguma
    // linha" deixaria o taxId antigo no lugar e os dois guards do
    // SendConversionFunding virariam teatro.
    $this->seed(DictEntrySeeder::class);

    config(['fake-starkbank-dict.funding.tax_id' => '11.222.333/0001-44']);

    $this->seed(DictEntrySeeder::class);

    expect(DictEntry::query()->where('pix_key', 'funding@fake-binance.dev')->firstOrFail()->tax_id)
        ->toBe('11.222.333/0001-44')
        ->and(DictEntry::query()->count())->toBe(2);
});
