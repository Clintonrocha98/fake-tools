<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| GET /v2/dict-key/{key}
|--------------------------------------------------------------------------
|
| A resolução que antecede todo cash-out. Fail-closed do lado de lá: o 404 aqui
| é o que vira PixKeyUnresolvable e segura o Payout em Withheld, em vez de
| transferir para um beneficiário em branco.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
});

it('resolve a chave semeada no envelope singular key', function (): void {
    $this->seed(DictEntrySeeder::class);

    $response = $this->getSigned('/v2/dict-key/'.rawurlencode('ada@brd.digital'), $this->signedHeaders());

    $response->assertOk()
        ->assertJsonPath('key.id', 'ada@brd.digital')
        ->assertJsonPath('key.type', 'email')
        ->assertJsonPath('key.name', 'Ada Lovelace')
        ->assertJsonPath('key.taxId', '012.345.678-90')
        ->assertJsonPath('key.ownerType', 'naturalPerson')
        ->assertJsonPath('key.bankName', 'Stark Bank S.A.')
        ->assertJsonPath('key.ispb', '20018183')
        ->assertJsonPath('key.accountType', 'checking')
        ->assertJsonPath('key.status', 'registered');
});

it('devolve a chave PIX no campo id, nunca a pk interna', function (): void {
    // DictKeyResponse do consumidor lê `id` como pixKey; devolver o uuid da
    // linha faria o POST /v2/transfer seguinte nomear um beneficiário
    // inexistente.
    $entry = DictEntry::factory()->create(['pix_key' => 'contato@brd.digital']);

    $this->getSigned('/v2/dict-key/'.rawurlencode('contato@brd.digital'), $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('key.id', 'contato@brd.digital')
        ->assertJsonMissing(['key' => ['id' => $entry->id]]);
});

it('serve agência e conta como blobs opacos prontos para o eco', function (): void {
    $this->seed(DictEntrySeeder::class);

    $key = (array) $this->getSigned('/v2/dict-key/'.rawurlencode('ada@brd.digital'), $this->signedHeaders())
        ->assertOk()
        ->json('key');

    expect((string) $key['branchCode'])->toStartWith('*')
        ->and((string) $key['accountNumber'])->toStartWith('*');
});

it('registra também a chave do funding cross-fake, com o CNPJ que o consumidor confere', function (): void {
    // O contrato entre os fakes viaja por constante de config replicada dos dois
    // lados — nunca por uma chamada HTTP de um fake ao outro.
    $this->seed(DictEntrySeeder::class);

    $this->getSigned('/v2/dict-key/'.rawurlencode('funding@fake-binance.dev'), $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('key.name', 'Fake Binance')
        ->assertJsonPath('key.taxId', '20.018.183/0001-80')
        ->assertJsonPath('key.ownerType', 'legalEntity')
        ->assertJsonPath('key.ispb', '20018183');
});

it('honra a chave de funding vinda do env, para casar com o BR Code do fake-binance', function (): void {
    config([
        'fake-starkbank-dict.funding.pix_key' => 'outra-chave@fake-binance.dev',
        'fake-starkbank-dict.funding.tax_id' => '11.222.333/0001-44',
    ]);

    $this->seed(DictEntrySeeder::class);

    $this->getSigned('/v2/dict-key/'.rawurlencode('outra-chave@fake-binance.dev'), $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('key.taxId', '11.222.333/0001-44');
});

it('responde 404 invalidDictKey no envelope de erro para uma chave fora do registro', function (): void {
    $this->getSigned('/v2/dict-key/'.rawurlencode('desconhecida@brd.digital'), $this->signedHeaders())
        ->assertStatus(404)
        ->assertHeader('content-type', 'application/json')
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidDictKey', 'message' => 'PIX key not found'],
            ],
        ]);
});

it('exige assinatura como toda rota do fake', function (): void {
    $this->getSigned('/v2/dict-key/'.rawurlencode('ada@brd.digital'))
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});
