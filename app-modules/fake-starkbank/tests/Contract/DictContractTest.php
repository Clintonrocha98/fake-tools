<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use PHPUnit\Framework\ExpectationFailedException;

uses(SignsRequests::class, AssertsRecordedShape::class);

/*
 * A resolução DICT contra o fixture gravado do consumidor. `DictKeyResponse` lê
 * `id` como a chave PIX e `ispb` como o banco; `branchCode`/`accountNumber` são
 * blobs opacos que ele ecoa verbatim no POST /v2/transfer.
 */

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->seed(DictEntrySeeder::class);
});

it('responde a resolução no shape do fixture dict_key', function (): void {
    $response = $this->getSigned('/v2/dict-key/'.rawurlencode('ada@brd.digital'), $this->signedHeaders());

    $response->assertOk();

    $fixture = $this->loadContractFixture('dict/dict_key.json');

    // `id` e `status` são literais: o primeiro é a chave que volta no transfer,
    // o segundo é vocabulário que comparar por tipo deixaria virar qualquer
    // palavra.
    $fixture['key']['id'] = $this->exactValue('ada@brd.digital');
    $fixture['key']['status'] = $this->exactValue('registered');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('serve os blobos no formato opaco que o consumidor ecoa sem parsear', function (): void {
    $key = (array) $this->getSigned('/v2/dict-key/'.rawurlencode('ada@brd.digital'), $this->signedHeaders())
        ->assertOk()
        ->json('key');

    expect((string) $key['branchCode'])->toMatch('#^\*[A-Za-z0-9+/]+=*$#')
        ->and((string) $key['accountNumber'])->toMatch('#^\*[A-Za-z0-9+/]+=*$#');
});

it('devolve o envelope de erro do StarkBank numa chave desconhecida, nunca HTML', function (): void {
    $response = $this->getSigned('/v2/dict-key/'.rawurlencode('ninguem@brd.digital'), $this->signedHeaders());

    $response->assertStatus(404)
        ->assertHeader('content-type', 'application/json');

    $fixture = $this->loadContractFixture('errors/error_envelope.json');
    $fixture['errors'][0]['code'] = $this->exactValue('invalidDictKey');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('quebra quando o ispb some, porque é ele que seleciona o trilho PIX', function (): void {
    // Prova do mecanismo: sem isto, a suíte de contrato é teatro. `bankIspb`
    // tem fallback para `bankCode` do lado de lá, mas servir nenhum dos dois
    // manda o transfer sem trilho.
    $fixture = $this->loadContractFixture('dict/dict_key.json');

    $drifted = $fixture;
    unset($drifted['key']['ispb']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando o ownerType é renomeado para snake_case', function (): void {
    $fixture = $this->loadContractFixture('dict/dict_key.json');

    $drifted = $fixture;
    $drifted['key']['owner_type'] = $drifted['key']['ownerType'];
    unset($drifted['key']['ownerType']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});
