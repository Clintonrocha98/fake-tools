<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Actions\AdvanceBrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Actions\ForceBrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Tests\Support\BuildsBrcodes;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsRequests::class, SignsWebhooks::class, BuildsBrcodes::class);

/*
|--------------------------------------------------------------------------
| Cenários da perna de BR Code
|--------------------------------------------------------------------------
|
| Os ganchos que o switchboard do painel vai embrulhar num botão: forçar a
| recusa do funding e tirar a chave do registro DICT. Aqui eles existem
| sozinhos — a UI chega no ticket de cenários armados.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->configureFakeStarkbankWebhook(url: null);
    config(['fake-starkbank-brcode.advance_seconds' => 60]);
});

it('força a recusa, o desfecho que o relógio nunca produz', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $recusado = resolve(ForceBrcodePaymentStatus::class)->handle($pagamento, BrcodePaymentStatus::Failed);

    expect($recusado->status)->toBe(BrcodePaymentStatus::Failed)
        ->and(WebhookEmission::query()->firstOrFail()->event_type)->toBe(StarkbankEventType::Failed);
});

it('grava o desfecho em vez de mascará-lo: a leitura seguinte não o desfaz', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    resolve(ForceBrcodePaymentStatus::class)->handle($pagamento, BrcodePaymentStatus::Failed);

    $this->travel(600)->seconds();

    expect(resolve(AdvanceBrcodePaymentStatus::class)->handle($pagamento->refresh())->status)
        ->toBe(BrcodePaymentStatus::Failed);
});

it('liquida na hora, encurtando a espera pelo avanço lazy', function (): void {
    $pagamento = BrcodePayment::factory()->create();

    $liquidado = resolve(ForceBrcodePaymentStatus::class)->handle($pagamento, BrcodePaymentStatus::Success);

    expect($liquidado->status)->toBe(BrcodePaymentStatus::Success)
        ->and(WebhookEmission::query()->firstOrFail()->event_type)->toBe(StarkbankEventType::Success);
});

it('limpa o motivo da recusa ao forçar a liquidação, e o envelope de success não anuncia uma recusa', function (): void {
    // Mesmo defeito da transfer: o `reason` do envelope sai de
    // `failure_reason`, e um funding liquidado não pode anunciar o motivo da
    // recusa que o antecedeu.
    $pagamento = BrcodePayment::factory()->failed()->create(['failure_reason' => 'chave PIX inválida']);

    $liquidado = resolve(ForceBrcodePaymentStatus::class)->handle($pagamento, BrcodePaymentStatus::Success);

    expect($liquidado->failure_reason)->toBeNull()
        ->and(WebhookEmission::query()->firstOrFail()->payload->decoded()['event']['log']['reason'] ?? null)->toBeNull();
});

it('preserva o motivo ao forçar a recusa', function (): void {
    $pagamento = BrcodePayment::factory()->create(['failure_reason' => 'chave PIX inválida']);

    $recusado = resolve(ForceBrcodePaymentStatus::class)->handle($pagamento, BrcodePaymentStatus::Failed);

    expect($recusado->failure_reason)->toBe('chave PIX inválida');
});

it('remover a chave do DICT torna o destino não verificável no preview', function (): void {
    // O cenário que exercita o guard `destinationUnverifiable` do consumidor
    // sem tocar no BR Code: o código continua o mesmo, o registro é que muda.
    $this->seed(DictEntrySeeder::class);

    $brcode = $this->staticBrcode();
    $uri = '/v2/brcode-preview?brcodes='.rawurlencode($brcode);

    $this->getSigned($uri, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('previews.0.taxId', '20.018.183/0001-80');

    DictEntry::query()->where('pix_key', 'funding@fake-binance.dev')->delete();

    $this->getSigned($uri, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('previews.0.taxId', '');
});
