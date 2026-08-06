<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(SignsWebhooks::class);

beforeEach(fn () => $this->configureFakeStarkbankWebhook());

it('reenvia apenas as emissões pendentes e as marca como entregues', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $pendente = WebhookEmission::factory()->create([
        'url' => $this->webhookUrl(),
        'payload' => '{"event":{"id":"1111111111111111"}}',
    ]);
    $entregue = WebhookEmission::factory()->delivered()->create([
        'url' => $this->webhookUrl(),
        'payload' => '{"event":{"id":"2222222222222222"}}',
    ]);

    $this->artisan('fake-starkbank:flush-webhooks')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->body() === '{"event":{"id":"1111111111111111"}}');

    expect($pendente->refresh()->sent_at)->not->toBeNull()
        ->and($pendente->response_code)->toBe(200)
        ->and($entregue->refresh()->sent_at?->toIso8601String())->toBe($entregue->sent_at?->toIso8601String());
});

it('reenvia com os bytes e a assinatura originais, sem remontar o envelope', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $pendente = WebhookEmission::factory()->failed()->create([
        'url' => $this->webhookUrl(),
        'payload' => '{"event":{"id":"3333333333333333"}}',
        'signature' => 'YXNzaW5hdHVyYS1ncmF2YWRh',
    ]);

    $this->artisan('fake-starkbank:flush-webhooks')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->body() === $pendente->payload->rawBody
        && $request->header('Digital-Signature') === ['YXNzaW5hdHVyYS1ncmF2YWRh']);

    expect($pendente->refresh()->failed_reason)->toBeNull();
});

it('avisa e não tenta nada quando não há pendência', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    WebhookEmission::factory()->delivered()->create(['url' => $this->webhookUrl()]);

    $this->artisan('fake-starkbank:flush-webhooks')
        ->expectsOutputToContain('Nenhuma emissão pendente')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('recupera a emissão que nasceu sem destino depois que a URL é configurada', function (): void {
    // O cenário que o flush existe para resolver: dev sobe o fake sem
    // FAKE_STARKBANK_WEBHOOK_URL, emite, configura a URL e roda o comando. A
    // emissão congelou `url` vazia ao nascer — sem adotar a configurada agora,
    // ela ficaria pendente para sempre, listada em todo flush e nunca entregue.
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $orfa = WebhookEmission::factory()->create([
        'url' => '',
        'payload' => '{"event":{"id":"4444444444444444"}}',
    ]);

    $this->artisan('fake-starkbank:flush-webhooks')->assertSuccessful();

    Http::assertSentCount(1);

    expect($orfa->refresh()->sent_at)->not->toBeNull()
        ->and($orfa->url)->toBe($this->webhookUrl());
});

it('segue pendente quando a emissão não tem destino e nenhuma URL está configurada', function (): void {
    $this->configureFakeStarkbankWebhook(url: null);
    Http::fake();

    $orfa = WebhookEmission::factory()->create(['url' => '']);

    $this->artisan('fake-starkbank:flush-webhooks')
        ->expectsOutputToContain('Ainda pendente')
        ->assertSuccessful();

    Http::assertNothingSent();

    expect($orfa->refresh()->sent_at)->toBeNull();
});

it('assina na entrega a emissão que nasceu sem assinatura e a entrega', function (): void {
    // Simetria com o destino: a emissão gravada sem PEM legível volta a ser
    // entregável assim que a chave aparece, sobre os MESMOS bytes gravados.
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $semAssinatura = WebhookEmission::factory()->create([
        'url' => $this->webhookUrl(),
        'payload' => '{"event":{"id":"5555555555555555"}}',
        'signature' => '',
        'failed_reason' => WebhookEmission::UNSIGNED_REASON,
    ]);

    $this->artisan('fake-starkbank:flush-webhooks')->assertSuccessful();

    $semAssinatura->refresh();

    expect($semAssinatura->sent_at)->not->toBeNull()
        ->and($semAssinatura->failed_reason)->toBeNull()
        ->and($this->verifyLikeConsumer($semAssinatura->payload->rawBody, $semAssinatura->signature))->toBeTrue();
});

it('não POSTa emissão sem assinatura enquanto não houver chave privada legível', function (): void {
    config(['fake-starkbank.webhook.private_key' => '', 'fake-starkbank.webhook.private_key_path' => null]);
    Http::fake();

    $semAssinatura = WebhookEmission::factory()->create(['url' => $this->webhookUrl(), 'signature' => '']);

    $this->artisan('fake-starkbank:flush-webhooks')->assertSuccessful();

    Http::assertNothingSent();

    expect($semAssinatura->refresh()->failed_reason)->toBe(WebhookEmission::UNSIGNED_REASON);
});

it('deixa pendente o que continua falhando, sem retry automático', function (): void {
    Http::fake([$this->webhookUrl() => Http::response('Unauthorized', 401)]);

    $pendente = WebhookEmission::factory()->create(['url' => $this->webhookUrl()]);

    $this->artisan('fake-starkbank:flush-webhooks')->assertSuccessful();

    Http::assertSentCount(1);

    expect($pendente->refresh()->sent_at)->toBeNull()
        ->and($pendente->failed_reason)->toContain('HTTP 401');
});
