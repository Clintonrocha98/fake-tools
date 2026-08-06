<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitCorrupted;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Support\Facades\Http;

uses(SignsWebhooks::class);

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook();
    Http::fake([$this->webhookUrl() => Http::response('Unauthorized', 401)]);
});

it('produz uma assinatura que não fecha contra a chave pública real do fake', function (): void {
    $emission = resolve(EmitCorrupted::class)(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    expect($emission)->toBeInstanceOf(WebhookEmission::class)
        ->and($this->verifyLikeConsumer($emission->payload->rawBody, $emission->signature))->toBeFalse();
});

it('monta um envelope bem formado — o que está errado é só a assinatura', function (): void {
    $emission = resolve(EmitCorrupted::class)(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $event = $emission?->payload->decoded()['event'] ?? [];

    expect($event['id'])->toMatch('/^\d{16}$/')
        ->and($event['subscription'])->toBe('invoice')
        ->and($event['log']['type'])->toBe('paid')
        ->and($event['log']['invoice']['id'])->toBe('5155165527080960');
});

it('percorre a mesma persistência e a mesma entrega pós-resposta da emissão normal', function (): void {
    $emission = resolve(EmitCorrupted::class)(StarkbankSubscription::Transfer, StarkbankEventType::Success, ['id' => '888']);

    expect($emission?->sent_at)->toBeNull();
    Http::assertNothingSent();

    $this->app->terminate();

    Http::assertSentCount(1);

    // O consumidor recusa com 401 — é exatamente o desfecho que o cenário arma.
    expect($emission?->refresh()->response_code)->toBe(401)
        ->and($emission?->sent_at)->toBeNull()
        ->and($emission?->failed_reason)->toContain('HTTP 401');
});

it('gera uma chave descartável diferente a cada emissão corrompida', function (): void {
    // Duas corrupções não podem colidir numa mesma chave: se colidissem, um
    // consumidor configurado com ela passaria a aceitar o cenário adverso.
    $primeira = resolve(EmitCorrupted::class)(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity('1'));
    $segunda = resolve(EmitCorrupted::class)(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity('1'));

    expect($primeira?->signature)->not->toBe($segunda?->signature);
});
