<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Actions\ReplayEmission;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(SignsWebhooks::class);

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook();
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);
});

it('reenvia os bytes originais, byte a byte, com a mesma assinatura', function (): void {
    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->app->terminate();

    resolve(ReplayEmission::class)($emission);

    /** @var list<Request> $enviados */
    $enviados = Http::recorded()->map(fn (array $par): Request => $par[0])->all();

    expect($enviados)->toHaveCount(2)
        // Comparação de BYTES, não de igualdade estrutural do JSON: reordenar
        // uma key produziria o mesmo array e uma assinatura que não fecha.
        ->and($enviados[1]->body())->toBe($enviados[0]->body())
        ->and($enviados[1]->header('Digital-Signature'))->toBe($enviados[0]->header('Digital-Signature'))
        ->and($enviados[1]->body())->toBe($emission?->payload->rawBody);
});

it('reusa o mesmo event.id, que é a chave de idempotência do consumidor', function (): void {
    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->app->terminate();

    resolve(ReplayEmission::class)($emission);

    expect(WebhookEmission::query()->count())->toBe(1);

    $corpos = Http::recorded()->map(fn (array $par): mixed => json_decode((string) $par[0]->body(), associative: true)['event']['id'])->all();

    expect($corpos[1])->toBe($corpos[0])
        ->and($corpos[0])->toBe($emission?->event_id);
});

it('entrega na hora, sem esperar o fim do request — quem dispara é o operador', function (): void {
    $emission = WebhookEmission::factory()->create([
        'url' => $this->webhookUrl(),
        'payload' => '{"event":{"id":"1234567890123456"}}',
    ]);

    resolve(ReplayEmission::class)($emission);

    Http::assertSentCount(1);

    expect($emission->refresh()->sent_at)->not->toBeNull()
        ->and($emission->response_code)->toBe(200);
});

it('reenvia também uma emissão que já tinha sido entregue', function (): void {
    // Replay não é "recuperação de pendente" (isso é o flush): é reexercitar a
    // idempotência do consumidor com um evento que ele já viu.
    $emission = WebhookEmission::factory()->delivered()->create(['url' => $this->webhookUrl()]);

    resolve(ReplayEmission::class)($emission);

    Http::assertSentCount(1);
});

it('não tenta o POST de uma emissão gravada sem destino', function (): void {
    $emission = WebhookEmission::factory()->create(['url' => '']);

    resolve(ReplayEmission::class)($emission);

    Http::assertNothingSent();

    expect($emission->refresh()->sent_at)->toBeNull();
});
