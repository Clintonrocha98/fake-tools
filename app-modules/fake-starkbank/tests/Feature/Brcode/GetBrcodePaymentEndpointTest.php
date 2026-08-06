<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| GET /v2/brcode-payment/{id}
|--------------------------------------------------------------------------
|
| A releitura autoritativa do funding — "webhook = trigger, GET = truth". É dela
| que ConfirmBrcodePaymentSettlement monta o Settlement Fact.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-brcode.advance_seconds' => 60]);
});

it('serve o envelope singular payment', function (): void {
    $pagamento = BrcodePayment::factory()->settled()->create(['tags' => ['conversion-abc-123']]);

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.id', $pagamento->id)
        ->assertJsonPath('payment.status', 'success')
        ->assertJsonPath('payment.amount', $pagamento->amount)
        ->assertJsonPath('payment.tags', ['conversion-abc-123']);
});

it('faz o tempo passar na leitura: created → processing → success sem ninguém empurrar', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'created');

    $this->travel(61)->seconds();

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'processing');

    $this->travel(60)->seconds();

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'success');

    expect($pagamento->refresh()->status)->toBe(BrcodePaymentStatus::Success);
});

it('salta direto para success numa leitura muito posterior', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->travel(300)->seconds();

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'success');
});

it('mostra na releitura o mesmo estado que a varredura do extrato', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->travel(121)->seconds();

    $doGet = (array) $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())->assertOk()->json('payment');
    $daLista = (array) $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders())->assertOk()->json('payments.0');

    expect($doGet['status'])->toBe('success')
        ->and($daLista)->toBe($doGet);
});

it('não move mais um pagamento que já teve desfecho', function (): void {
    $pagamento = BrcodePayment::factory()->failed()->create();

    $this->travel(600)->seconds();

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'failed');
});

it('congela o ciclo quando advance_seconds é zero', function (): void {
    config(['fake-starkbank-brcode.advance_seconds' => 0]);

    $pagamento = BrcodePayment::factory()->create();

    $this->travel(600)->seconds();

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'created');
});

it('responde 404 invalidId no envelope de erro para um id que este fake nunca criou', function (): void {
    $this->getSigned('/v2/brcode-payment/5824000009469952', $this->signedHeaders())
        ->assertStatus(404)
        ->assertHeader('content-type', 'application/json')
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidId', 'message' => 'Invalid id'],
            ],
        ]);
});

it('exige assinatura como toda rota do fake', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $this->getSigned('/v2/brcode-payment/'.$pagamento->id)
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});
