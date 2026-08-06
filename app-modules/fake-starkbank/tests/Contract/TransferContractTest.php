<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use PHPUnit\Framework\ExpectationFailedException;

uses(SignsRequests::class, AssertsRecordedShape::class);

/*
 * Os três shapes de transfer contra os fixtures gravados do consumidor. Ao
 * contrário da invoice, os três são o MESMO conjunto de keys: o eco do POST, a
 * releitura singular e o item da listagem. Repetir aqui a assimetria da invoice
 * seria inventá-la.
 */

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);
});

it('responde o despacho no shape do fixture transfer_created', function (): void {
    $response = $this->postSigned('/v2/transfer', ['transfers' => [[
        'amount' => 5_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'bankCode' => '20018183',
        'branchCode' => '*2Nq4Yw7cBw9utBZAgLOg4kKm8xNXJkYkqUzletoeOPM=',
        'accountNumber' => '*PnAoBLxISgcZ4Widbyqv0rvQx/NWaFn5NQXg/LpyFyIC',
        'accountType' => 'checking',
        'externalId' => 'payout-abc-123',
        'tags' => ['payout-abc-123'],
    ]]]);

    $response->assertOk();

    $fixture = $this->loadContractFixture('transfer/transfer_created.json');

    // `status` na criação é literal: o consumidor classifica o Settlement por
    // esta string, e comparar só o tipo deixaria passar qualquer palavra.
    $fixture['transfers'][0]['status'] = $this->exactValue('created');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('responde a releitura no shape do fixture transfer_settled', function (): void {
    $transfer = Transfer::factory()->settled()->create(['tags' => ['payout-abc-123']]);

    $response = $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders());

    $response->assertOk();

    $fixture = $this->loadContractFixture('transfer/transfer_settled.json');
    $fixture['transfer']['status'] = $this->exactValue('success');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('responde o extrato no shape do fixture transfer_list_settled', function (): void {
    Transfer::factory()->settled()->create(['tags' => ['payout-abc-123']]);

    $response = $this->getSigned('/v2/transfer?status=success', $this->signedHeaders());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('transfer/transfer_list_settled.json'),
        (array) $response->json(),
    );

    $response->assertJsonPath('cursor', null);
});

it('serve o mesmo conjunto de keys no eco, na releitura e na lista', function (): void {
    $doPost = (array) $this->postSigned('/v2/transfer', ['transfers' => [[
        'amount' => 5_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'bankCode' => '20018183',
        'branchCode' => '0001-x',
        'accountNumber' => '98765-4',
        'externalId' => 'payout-abc-123',
        'tags' => ['payout-abc-123'],
    ]]])->assertOk()->json('transfers.0');

    $id = (string) $doPost['id'];

    $doGet = (array) $this->getSigned('/v2/transfer/'.$id, $this->signedHeaders())->assertOk()->json('transfer');
    $daLista = (array) $this->getSigned('/v2/transfer', $this->signedHeaders())->assertOk()->json('transfers.0');

    expect(array_keys($doGet))->toEqualCanonicalizing(array_keys($doPost))
        ->and(array_keys($daLista))->toEqualCanonicalizing(array_keys($doPost));
});

it('nunca produz a forma que o consumidor lê como malformed', function (): void {
    // Um 200 com `transfers` vazio ou sem `id` vira StarkbankRequestFailed::malformed()
    // do lado de lá — e um Payout que nunca sela.
    $response = $this->postSigned('/v2/transfer', ['transfers' => [[
        'amount' => 5_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'bankCode' => '20018183',
        'branchCode' => '0001-x',
        'accountNumber' => '98765-4',
    ]]])->assertOk();

    expect($response->json('transfers'))->toBeArray()->not->toBeEmpty()
        ->and((string) $response->json('transfers.0.id'))->not->toBeEmpty();
});

it('serve datas que o consumidor materializa em Carbon', function (): void {
    $transfer = Transfer::factory()->settled()->create();

    $lido = (array) $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())->assertOk()->json('transfer');

    foreach (['created', 'updated'] as $campo) {
        expect(CarbonImmutable::parse((string) $lido[$campo])->toIso8601String())->toBeString();
    }
});

it('devolve o envelope de erro do StarkBank num id desconhecido, nunca HTML', function (): void {
    $response = $this->getSigned('/v2/transfer/5155165527080960', $this->signedHeaders());

    $response->assertStatus(404)
        ->assertHeader('content-type', 'application/json');

    $fixture = $this->loadContractFixture('errors/error_envelope.json');
    $fixture['errors'][0]['code'] = $this->exactValue('invalidId');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('quebra quando o amount deixa de ser inteiro de centavos', function (): void {
    // Prova do mecanismo: sem isto, a suíte de contrato é teatro.
    $fixture = $this->loadContractFixture('transfer/transfer_settled.json');

    $drifted = $fixture;
    $drifted['transfer']['amount'] = '50.00';

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando as tags somem, porque é delas que sai o correlationId', function (): void {
    $fixture = $this->loadContractFixture('transfer/transfer_settled.json');

    $drifted = $fixture;
    unset($drifted['transfer']['tags']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});
