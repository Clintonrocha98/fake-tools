<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(SignsWebhooks::class);

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook();
    config(['fake-starkbank.workspace.id' => '6341320293482496']);
});

it('assina o raw body com a chave do fake e o consumidor consegue verificar', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    expect($emission)->toBeInstanceOf(WebhookEmission::class)
        ->and($this->verifyLikeConsumer($emission->payload->rawBody, $emission->signature))->toBeTrue();
});

it('grava a emissão com sent_at nulo antes de qualquer POST sair', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    expect($emission?->sent_at)->toBeNull()
        ->and($emission?->response_code)->toBeNull()
        ->and($emission?->url)->toBe($this->webhookUrl());

    Http::assertNothingSent();
});

it('entrega os MESMOS bytes assinados, com a assinatura no header Digital-Signature', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->app->terminate();

    Http::assertSent(fn (Request $request): bool => $request->url() === $this->webhookUrl()
        && $request->method() === 'POST'
        && $request->body() === $emission?->payload->rawBody
        && $request->header('Digital-Signature') === [$emission->signature]);

    expect($emission?->refresh()->sent_at)->not->toBeNull()
        ->and($emission?->response_code)->toBe(200)
        ->and($emission?->failed_reason)->toBeNull();
});

it('monta o envelope com o workspaceId da config e a entity inteira sob a key da subscription', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::BrcodePayment, StarkbankEventType::Success, ['id' => '9911', 'status' => 'success']);

    $event = $emission?->payload->decoded()['event'] ?? [];

    expect($event['workspaceId'])->toBe('6341320293482496')
        ->and($event['subscription'])->toBe('brcode-payment')
        ->and($event['log']['type'])->toBe('success')
        ->and($event['log']['payment'])->toBe(['id' => '9911', 'status' => 'success'])
        ->and($emission?->entity_id)->toBe('9911');
});

it('gera event.id e log.id numéricos de 16 dígitos, distintos entre si', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $event = $emission?->payload->decoded()['event'] ?? [];

    expect($event['id'])->toMatch('/^\d{16}$/')
        ->and($event['log']['id'])->toMatch('/^\d{16}$/')
        ->and($event['id'])->not->toBe($event['log']['id'])
        ->and($emission?->event_id)->toBe($event['id']);
});

it('marca a falha sem retry quando o consumidor recusa a entrega', function (): void {
    // 401 do outro lado é o que EmitCorrupted produz de propósito — aqui só
    // interessa que a emissão fica pendente e com o motivo legível.
    Http::fake([$this->webhookUrl() => Http::response('Unauthorized', 401)]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->app->terminate();

    expect($emission?->refresh()->sent_at)->toBeNull()
        ->and($emission?->response_code)->toBe(401)
        ->and($emission?->failed_reason)->toContain('HTTP 401');

    Http::assertSentCount(1);
});

it('marca a falha de rede sem derrubar o request que originou o evento', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('Connection refused'));

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->app->terminate();

    expect($emission?->refresh()->sent_at)->toBeNull()
        ->and($emission?->response_code)->toBeNull()
        ->and($emission?->failed_reason)->toContain('Connection refused');
});

it('vira no-op logado sem destino configurado: grava a emissão e não tenta o POST', function (): void {
    Http::fake();
    config(['fake-starkbank.webhook.url' => null]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->app->terminate();

    expect($emission)->toBeInstanceOf(WebhookEmission::class)
        ->and($emission->url)->toBeEmpty()
        ->and($emission->sent_at)->toBeNull();

    Http::assertNothingSent();
});

it('grava a emissão sem assinatura, com o motivo à vista, quando não há chave privada legível', function (): void {
    // Simetria com o destino não configurado logo acima: a transição de estado
    // já aconteceu no banco, e a fila de emissões é a única trilha que esta
    // perna tem. Abortar antes do insert deixaria o painel vazio e o replay sem
    // nada para reenviar depois de configurar o PEM.
    Http::fake();
    config([
        'fake-starkbank.webhook.private_key' => null,
        'fake-starkbank.webhook.private_key_path' => null,
    ]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->app->terminate();

    expect($emission)->toBeInstanceOf(WebhookEmission::class)
        ->and($emission->signature)->toBeEmpty()
        ->and($emission->failed_reason)->toBe(WebhookEmission::UNSIGNED_REASON)
        ->and($emission->sent_at)->toBeNull()
        ->and(WebhookEmission::query()->pending()->count())->toBe(1);

    Http::assertNothingSent();
});

it('descarta a emissão quando o vocabulário do contrato não é do StarkBank', function (): void {
    Http::fake();

    resolve(EmitsWebhookEvents::class)->emit('deposit', 'paid', ['id' => '1']);
    resolve(EmitsWebhookEvents::class)->emit('invoice', 'teleported', ['id' => '1']);

    expect(WebhookEmission::query()->count())->toBe(0);
});

it('emite pelo contrato com o vocabulário de wire em string', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    resolve(EmitsWebhookEvents::class)->emit('brcode-payment', 'failed', ['id' => '4242']);

    $emission = WebhookEmission::query()->sole();

    expect($emission->subscription)->toBe(StarkbankSubscription::BrcodePayment)
        ->and($emission->event_type)->toBe(StarkbankEventType::Failed)
        ->and($emission->entity_id)->toBe('4242');
});

it('emite mesmo um par subscription/log type fora do ciclo de vida, para exercitar robustez', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Success, $this->invoiceEntity());

    expect($emission?->event_type)->toBe(StarkbankEventType::Success);
});
