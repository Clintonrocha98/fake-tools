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
| GET /v2/transfer/{id}
|--------------------------------------------------------------------------
|
| A releitura autoritativa do cash-out — "webhook = trigger, GET = truth". É
| dela que ConfirmTransferSettlement monta o Settlement Fact.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);
});

it('serve o envelope singular transfer', function (): void {
    $transfer = Transfer::factory()->settled()->create(['tags' => ['payout-abc-123']]);

    $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.id', $transfer->id)
        ->assertJsonPath('transfer.status', 'success')
        ->assertJsonPath('transfer.amount', $transfer->amount)
        ->assertJsonPath('transfer.tags', ['payout-abc-123']);
});

it('faz o tempo passar na leitura: created → processing → success sem ninguém empurrar', function (): void {
    $transfer = Transfer::factory()->create();

    $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'created');

    $this->travel(61)->seconds();

    $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'processing');

    $this->travel(60)->seconds();

    $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'success');

    expect($transfer->refresh()->status)->toBe(TransferStatus::Success);
});

it('salta direto para success numa leitura muito posterior', function (): void {
    // O estado intermediário que ninguém observou não muda nada do lado do
    // consumidor, que relê por GET.
    $transfer = Transfer::factory()->create();

    $this->travel(300)->seconds();

    $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'success');
});

it('mostra na releitura o mesmo estado que a varredura do extrato', function (): void {
    // Se o GET e o extrato divergissem, a conciliação e a rede de segurança
    // montariam dois Settlement Facts diferentes para o mesmo Payout.
    $transfer = Transfer::factory()->create();

    $this->travel(121)->seconds();

    $doGet = (array) $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())->assertOk()->json('transfer');
    $daLista = (array) $this->getSigned('/v2/transfer?status=success', $this->signedHeaders())->assertOk()->json('transfers.0');

    expect($doGet['status'])->toBe('success')
        ->and($daLista)->toBe($doGet);
});

it('não move mais uma transfer que já teve desfecho', function (): void {
    $transfer = Transfer::factory()->failed()->create();

    $this->travel(600)->seconds();

    $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'failed');
});

it('congela o ciclo quando advance_seconds é zero', function (): void {
    config(['fake-starkbank-transfer.advance_seconds' => 0]);

    $transfer = Transfer::factory()->create();

    $this->travel(600)->seconds();

    $this->getSigned('/v2/transfer/'.$transfer->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'created');
});

it('responde 404 invalidId no envelope de erro para um id que este fake nunca despachou', function (): void {
    $this->getSigned('/v2/transfer/5155165527080960', $this->signedHeaders())
        ->assertStatus(404)
        ->assertHeader('content-type', 'application/json')
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidId', 'message' => 'Invalid id'],
            ],
        ]);
});

it('exige assinatura como toda rota do fake', function (): void {
    $transfer = Transfer::factory()->create();

    $this->getSigned('/v2/transfer/'.$transfer->id)
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});
