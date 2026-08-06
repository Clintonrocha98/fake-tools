<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| POST /v2/transfer
|--------------------------------------------------------------------------
|
| O despacho do cash-out: envelope plural na entrada e na saída, e idempotência
| por externalId — o contrato do provedor que impede pagar o mesmo Payout duas
| vezes.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{transfers: list<array<string, mixed>>}
 */
function transferPayload(array $overrides = []): array
{
    return ['transfers' => [array_merge([
        'amount' => 5_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'bankCode' => '20018183',
        'branchCode' => '*2Nq4Yw7cBw9utBZAgLOg4kKm8xNXJkYkqUzletoeOPM=',
        'accountNumber' => '*PnAoBLxISgcZ4Widbyqv0rvQx/NWaFn5NQXg/LpyFyIC',
        'accountType' => 'checking',
        'externalId' => 'payout-abc-123',
        'tags' => ['payout-abc-123'],
    ], $overrides)]];
}

it('despacha o cash-out no envelope plural com o id e o status de partida', function (): void {
    $response = $this->postSigned('/v2/transfer', transferPayload());

    $response->assertOk()
        ->assertJsonCount(1, 'transfers')
        ->assertJsonPath('transfers.0.amount', 5_000)
        ->assertJsonPath('transfers.0.status', 'created')
        ->assertJsonPath('transfers.0.name', 'Ada Lovelace')
        ->assertJsonPath('transfers.0.taxId', '012.345.678-90')
        ->assertJsonPath('transfers.0.bankCode', '20018183')
        ->assertJsonPath('transfers.0.accountType', 'checking')
        ->assertJsonPath('transfers.0.tags', ['payout-abc-123']);

    expect((string) $response->json('transfers.0.id'))->toMatch('/^\d{16}$/');
});

it('nunca ecoa de volta os blobos de ida do DICT', function (): void {
    // No provedor real `branchCode`/`accountNumber` são dados opacos de ida;
    // ecoá-los convidaria algum consumidor futuro a lê-los.
    $transfer = (array) $this->postSigned('/v2/transfer', transferPayload())->assertOk()->json('transfers.0');

    expect(array_keys($transfer))->toEqualCanonicalizing([
        'id', 'amount', 'name', 'taxId', 'bankCode', 'accountType', 'status', 'tags', 'created', 'updated',
    ]);
});

it('grava a transfer com os blobos verbatim e o correlationId na primeira tag', function (): void {
    $id = (string) $this->postSigned('/v2/transfer', transferPayload())->assertOk()->json('transfers.0.id');

    $transfer = Transfer::query()->findOrFail($id);

    expect($transfer->status)->toBe(TransferStatus::Created)
        ->and($transfer->branch_code)->toBe('*2Nq4Yw7cBw9utBZAgLOg4kKm8xNXJkYkqUzletoeOPM=')
        ->and($transfer->account_number)->toBe('*PnAoBLxISgcZ4Widbyqv0rvQx/NWaFn5NQXg/LpyFyIC')
        ->and($transfer->external_id)->toBe('payout-abc-123')
        ->and($transfer->tags->correlationId())->toBe('payout-abc-123');
});

it('aceita qualquer string nos blobos de agência e conta, porque são opacos por contrato', function (): void {
    $this->postSigned('/v2/transfer', transferPayload([
        'branchCode' => '0001-x',
        'accountNumber' => '98765-4',
    ]))->assertOk();

    expect(Transfer::query()->firstOrFail()->branch_code)->toBe('0001-x');
});

it('devolve a MESMA transfer num segundo POST com o mesmo externalId', function (): void {
    $primeiro = (string) $this->postSigned('/v2/transfer', transferPayload())->assertOk()->json('transfers.0.id');
    $segundo = (string) $this->postSigned('/v2/transfer', transferPayload())->assertOk()->json('transfers.0.id');

    expect($segundo)->toBe($primeiro)
        ->and(Transfer::query()->count())->toBe(1);
});

it('devolve na repetição o estado ATUAL, não o de partida', function (): void {
    // O retry do consumidor é uma leitura como outra qualquer: o avanço lazy
    // roda e ele já vê a liquidação, sem precisar de um GET extra.
    $this->postSigned('/v2/transfer', transferPayload())->assertOk();

    $this->travel(121)->seconds();

    $this->postSigned('/v2/transfer', transferPayload())
        ->assertOk()
        ->assertJsonPath('transfers.0.status', 'success');

    expect(Transfer::query()->count())->toBe(1);
});

it('cria transfers distintas quando o consumidor não manda externalId', function (): void {
    // Sem chave de idempotência não há o que deduplicar: dois pagamentos de
    // mesmo valor para o mesmo beneficiário são legítimos.
    $payload = transferPayload();
    unset($payload['transfers'][0]['externalId']);

    $this->postSigned('/v2/transfer', $payload)->assertOk();
    $this->postSigned('/v2/transfer', $payload)->assertOk();

    expect(Transfer::query()->count())->toBe(2);
});

it('aceita N transfers no array, mesmo que o consumidor mande sempre uma', function (): void {
    $payload = ['transfers' => [
        transferPayload(['externalId' => 'payout-1', 'tags' => ['payout-1']])['transfers'][0],
        transferPayload(['amount' => 25_000, 'externalId' => 'payout-2', 'tags' => ['payout-2']])['transfers'][0],
    ]];

    $this->postSigned('/v2/transfer', $payload)
        ->assertOk()
        ->assertJsonCount(2, 'transfers')
        ->assertJsonPath('transfers.1.amount', 25_000);

    expect(Transfer::query()->count())->toBe(2);
});

it('assume conta corrente quando o accountType não vem', function (): void {
    $payload = transferPayload();
    unset($payload['transfers'][0]['accountType']);

    $this->postSigned('/v2/transfer', $payload)
        ->assertOk()
        ->assertJsonPath('transfers.0.accountType', 'checking');
});

it('recusa o despacho inválido no envelope de erro do StarkBank, nunca no 422 do Laravel', function (array $override, string $trecho): void {
    $payload = transferPayload();
    $payload['transfers'][0] = array_merge($payload['transfers'][0], $override);

    $response = $this->postSigned('/v2/transfer', $payload);

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');

    expect((string) $response->json('errors.0.message'))->toContain($trecho);

    expect(Transfer::query()->count())->toBe(0);
})->with([
    'amount ausente' => [['amount' => null], 'amount'],
    'amount zerado' => [['amount' => 0], 'amount'],
    'nome ausente' => [['name' => null], 'name'],
    'taxId ausente' => [['taxId' => null], 'taxId'],
    'bankCode ausente' => [['bankCode' => null], 'bankCode'],
    'branchCode ausente' => [['branchCode' => null], 'branchCode'],
    'accountNumber ausente' => [['accountNumber' => null], 'accountNumber'],
    'accountType fora do vocabulário' => [['accountType' => 'poupancinha'], 'accountType'],
]);

it('recusa o envelope sem o array transfers', function (): void {
    $this->postSigned('/v2/transfer', ['transfers' => []])
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});

it('assina sobre o corpo: um body trocado depois de assinado não autentica', function (): void {
    $headers = $this->signedHeaders($this->jsonBody(transferPayload()));

    $this->postSigned('/v2/transfer', transferPayload(['amount' => 999_999]), $headers)
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'invalidSignature');

    expect(Transfer::query()->count())->toBe(0);
});
