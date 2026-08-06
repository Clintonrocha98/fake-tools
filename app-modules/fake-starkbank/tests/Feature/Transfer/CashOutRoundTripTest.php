<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsRequests::class, SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| A perna de cash-out inteira, ponta a ponta
|--------------------------------------------------------------------------
|
| Resolver a chave semeada → transferir com os blobs ecoados verbatim → deixar
| o relógio correr → a varredura de extrato liquidar sozinha. É este o caminho
| que leva o Payout do consumidor de Sent a Settled em dev, sem scheduler
| nenhum dos dois lados.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->configureFakeStarkbankWebhook(url: null);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);

    $this->seed(DictEntrySeeder::class);
});

it('resolve, transfere, avança e liquida numa varredura, sem ninguém empurrar', function (): void {
    $key = (array) $this->getSigned('/v2/dict-key/'.rawurlencode('ada@brd.digital'), $this->signedHeaders())
        ->assertOk()
        ->json('key');

    // O consumidor ecoa os blobs do DICT verbatim — nunca os parseia.
    $payload = ['transfers' => [[
        'amount' => 5_000,
        'name' => (string) $key['name'],
        'taxId' => (string) $key['taxId'],
        'bankCode' => (string) $key['ispb'],
        'branchCode' => (string) $key['branchCode'],
        'accountNumber' => (string) $key['accountNumber'],
        'accountType' => (string) $key['accountType'],
        'externalId' => 'payout-abc-123',
        'tags' => ['payout-abc-123'],
    ]]];

    $id = (string) $this->postSigned('/v2/transfer', $payload)
        ->assertOk()
        ->assertJsonPath('transfers.0.status', 'created')
        ->json('transfers.0.id');

    $this->travel(121)->seconds();

    $this->getSigned('/v2/transfer?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'transfers')
        ->assertJsonPath('transfers.0.id', $id)
        ->assertJsonPath('transfers.0.tags', ['payout-abc-123']);

    // A releitura autoritativa concorda com o extrato, e o webhook saiu como
    // gatilho do mesmo desfecho.
    $this->getSigned('/v2/transfer/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'success');

    $emission = WebhookEmission::query()->firstOrFail();

    expect($emission->event_type)->toBe(StarkbankEventType::Success)
        ->and($emission->entity_id)->toBe($id);
});

it('produz o fail-closed do consumidor quando a chave está fora do registro', function (): void {
    // O 404 aqui é o que vira PixKeyUnresolvable lá e segura o Payout em
    // Withheld, antes de qualquer POST /v2/transfer.
    $this->getSigned('/v2/dict-key/'.rawurlencode('ninguem@brd.digital'), $this->signedHeaders())
        ->assertStatus(404)
        ->assertJsonPath('errors.0.code', 'invalidDictKey')
        ->assertJsonPath('errors.0.message', 'PIX key not found');
});
