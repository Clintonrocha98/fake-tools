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

it('deixa pendente o que continua falhando, sem retry automático', function (): void {
    Http::fake([$this->webhookUrl() => Http::response('Unauthorized', 401)]);

    $pendente = WebhookEmission::factory()->create(['url' => $this->webhookUrl()]);

    $this->artisan('fake-starkbank:flush-webhooks')->assertSuccessful();

    Http::assertSentCount(1);

    expect($pendente->refresh()->sent_at)->toBeNull()
        ->and($pendente->failed_reason)->toContain('HTTP 401');
});
