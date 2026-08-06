<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| GET /v2/invoice/{id}
|--------------------------------------------------------------------------
|
| A releitura autoritativa — "webhook = trigger, GET = truth". É ela, e não um
| scheduler, que faz o tempo passar no fake.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-invoice.advance_seconds' => 60]);
});

it('serve o envelope singular no shape magro da releitura', function (): void {
    $invoice = Invoice::factory()->paid()->create(['tags' => ['deposit-abc-123']]);

    $response = $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders());

    $response->assertOk()
        ->assertJsonPath('invoice.id', $invoice->id)
        ->assertJsonPath('invoice.status', 'paid')
        ->assertJsonPath('invoice.amount', $invoice->amount)
        ->assertJsonPath('invoice.tags', ['deposit-abc-123']);

    expect(array_keys((array) $response->json('invoice')))->toEqualCanonicalizing([
        'id',
        'amount',
        'name',
        'taxId',
        'status',
        'brcode',
        'tags',
        'created',
        'due',
        'expiration',
        'updated',
    ]);
});

it('nunca ecoa na releitura os campos que só a emissão devolve', function (): void {
    // O GET do StarkBank real é mais magro que o POST; servir o shape gordo aqui
    // faria o fake aceitar um consumidor que o provedor de verdade quebraria.
    $invoice = Invoice::factory()->create();

    $lido = (array) $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders())->assertOk()->json('invoice');

    expect($lido)->not->toHaveKeys(['nominalAmount', 'fee', 'fine', 'interest', 'discounts', 'rules', 'splits', 'transactionIds', 'link', 'pdf']);
});

it('faz o tempo passar na leitura: a invoice madura aparece paga sem ninguém empurrar', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', 'created');

    $this->travel(61)->seconds();

    $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', 'paid');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at)->not->toBeNull();
});

it('mostra na releitura o mesmo estado que a varredura do extrato — o webhook é só o gatilho', function (): void {
    // ConfirmInvoiceSettlement do consumidor relê por GET quando o webhook
    // chega; se o GET e o extrato divergissem, a conciliação e a rede de
    // segurança montariam dois Settlement Facts diferentes para o mesmo Deposit.
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $this->travel(61)->seconds();

    $doGet = (array) $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders())->assertOk()->json('invoice');
    $daLista = (array) $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders())->assertOk()->json('invoices.0');

    expect($doGet['status'])->toBe('paid')
        ->and($daLista)->toBe($doGet);
});

it('responde 404 invalidId no envelope de erro para um id que este fake nunca emitiu', function (): void {
    $this->getSigned('/v2/invoice/5155165527080960', $this->signedHeaders())
        ->assertStatus(404)
        ->assertHeader('content-type', 'application/json')
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidId', 'message' => 'Invalid id'],
            ],
        ]);
});

it('exige assinatura como toda rota do fake', function (): void {
    $invoice = Invoice::factory()->create();

    $this->getSigned('/v2/invoice/'.$invoice->id)
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});
