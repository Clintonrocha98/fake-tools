<?php

declare(strict_types=1);

use He4rt\Control\Feed\Models\ControlEvent;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Invoice\Actions\ForceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

/*
|--------------------------------------------------------------------------
| Comandos da malha PIX
|--------------------------------------------------------------------------
|
| Cada POST envelopa a MESMA Action que o Resource do painel invoca: depois
| deste ticket o painel e o `/control` são dois consumidores das mesmas Actions,
| e o teste ponta a ponta do consumidor não precisa mais de navegador aberto.
|
*/

beforeEach(function (): void {
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
});

it('força o status da invoice e o evento aparece no feed', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $this->postJson('/control/starkbank/invoices/'.$invoice->id.'/force', ['status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('invoice.status', 'paid');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(ControlEvent::query()->where('channel', 'starkbank')->count())->toBeGreaterThan(0);
});

it('recusa com 422 um status que a invoice não conhece', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $resposta = $this->postJson('/control/starkbank/invoices/'.$invoice->id.'/force', ['status' => 'liquidada'])
        ->assertStatus(422);

    expect($resposta->json('valid'))->toContain('paid');
});

it('responde 404 numa invoice que não existe', function (): void {
    $this->postJson('/control/starkbank/invoices/000000/force', ['status' => 'paid'])->assertNotFound();
});

it('congela e descongela a invoice com valor explícito', function (): void {
    $invoice = Invoice::factory()->create(['frozen' => false, 'due' => now()->addDay()]);

    $this->postJson('/control/starkbank/invoices/'.$invoice->id.'/freeze', ['frozen' => true])
        ->assertOk()
        ->assertJsonPath('invoice.frozen', true);

    expect($invoice->refresh()->frozen)->toBeTrue();

    $this->postJson('/control/starkbank/invoices/'.$invoice->id.'/freeze', ['frozen' => false])->assertOk();

    expect($invoice->refresh()->frozen)->toBeFalse();
});

it('avança a invoice madura sob comando', function (): void {
    config(['fake-starkbank-invoice.advance_seconds' => 1]);

    $invoice = Invoice::factory()->create(['created_at' => now()->subHour(), 'due' => now()->addDay()]);

    $this->postJson('/control/starkbank/invoices/'.$invoice->id.'/advance')->assertOk();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
});

it('força e avança a transfer', function (): void {
    config(['fake-starkbank-transfer.advance_seconds' => 1]);

    $transfer = Transfer::factory()->create(['created_at' => now()->subHour()]);

    $this->postJson('/control/starkbank/transfers/'.$transfer->id.'/advance')->assertOk();

    expect($transfer->refresh()->status)->not->toBe(TransferStatus::Created);

    $this->postJson('/control/starkbank/transfers/'.$transfer->id.'/force', ['status' => 'failed'])
        ->assertOk()
        ->assertJsonPath('transfer.status', 'failed');
});

it('força e avança o brcode payment', function (): void {
    config(['fake-starkbank-brcode.advance_seconds' => 1]);

    $payment = BrcodePayment::factory()->create(['created_at' => now()->subHour()]);

    $this->postJson('/control/starkbank/brcode-payments/'.$payment->id.'/advance')->assertOk();

    expect($payment->refresh()->status)->not->toBe(BrcodePaymentStatus::Created);

    $this->postJson('/control/starkbank/brcode-payments/'.$payment->id.'/force', ['status' => 'failed'])
        ->assertOk()
        ->assertJsonPath('brcodePayment.status', 'failed');
});

it('reenvia uma emissão gravada mantendo o mesmo event_id', function (): void {
    $emissao = WebhookEmission::factory()->create();

    $this->postJson('/control/starkbank/emissions/'.$emissao->id.'/replay')
        ->assertOk()
        ->assertJsonPath('emission.eventId', $emissao->event_id);
});

it('libera uma emissão represada e trata a liberação repetida como no-op', function (): void {
    $emissao = WebhookEmission::factory()->create(['held_at' => now(), 'sent_at' => null]);

    $this->postJson('/control/starkbank/emissions/'.$emissao->id.'/release')->assertOk();

    expect($emissao->refresh()->held_at)->toBeNull();

    $this->postJson('/control/starkbank/emissions/'.$emissao->id.'/release')->assertOk();

    expect($emissao->refresh()->held_at)->toBeNull();
});

it('reemite uma emissão corrompida como um evento novo', function (): void {
    $emissao = WebhookEmission::factory()->create();

    $this->postJson('/control/starkbank/emissions/'.$emissao->id.'/emit-corrupted')
        ->assertCreated();

    expect(WebhookEmission::query()->count())->toBe(2);
});

it('responde 404 numa emissão que não existe', function (): void {
    $this->postJson('/control/starkbank/emissions/'.fake()->uuid().'/replay')->assertNotFound();
});

it('registra uma chave DICT', function (): void {
    $this->postJson('/control/starkbank/dict-entries', [
        'pixKey' => 'grace@brd.digital',
        'type' => 'email',
        'name' => 'Grace Hopper',
        'taxId' => '098.765.432-10',
        'ownerType' => 'naturalPerson',
    ])
        ->assertCreated()
        ->assertJsonPath('dictEntry.pixKey', 'grace@brd.digital');

    expect(DictEntry::query()->where('pix_key', 'grace@brd.digital')->exists())->toBeTrue();
});

it('deixa o estado indistinguível entre o gesto por HTTP e o mesmo gesto pelo painel', function (): void {
    // A Action é a mesma; este teste é a rede que pega uma futura reimplementação
    // do gesto dentro do controller.
    $pelaAction = Invoice::factory()->create(['due' => now()->addDay()]);
    $peloHttp = Invoice::factory()->create(['due' => now()->addDay()]);

    resolve(ForceInvoiceStatus::class)->handle($pelaAction, InvoiceStatus::Expired);

    $this->postJson('/control/starkbank/invoices/'.$peloHttp->id.'/force', ['status' => 'expired'])->assertOk();

    expect($peloHttp->refresh()->status)->toBe($pelaAction->refresh()->status)
        ->and($peloHttp->destined_status)->toBe($pelaAction->destined_status)
        ->and($peloHttp->frozen)->toBe($pelaAction->frozen);
});

it('recusa o registro DICT sem os campos do titular', function (): void {
    $this->postJson('/control/starkbank/dict-entries', ['pixKey' => 'grace@brd.digital'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type', 'name', 'taxId', 'ownerType']);
});
