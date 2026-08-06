<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\Enums\WebhookOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| Perna POR EVENTO: webhook
|--------------------------------------------------------------------------
|
| Diferente das três assíncronas: o armado é consumido no instante da EMISSÃO,
| não na criação do recurso de origem. "Armar" aqui vale para o próximo evento.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook();
});

function emitArmedWebhook(): ?WebhookEmission
{
    $emission = resolve(EmitWebhookEvent::class)->handle(
        StarkbankSubscription::Invoice,
        StarkbankEventType::Paid,
        test()->invoiceEntity(),
    );

    // Os callbacks de dispatchAfterResponse só rodam no terminate do kernel, e
    // esta emissão saiu de uma chamada direta à Action, sem hit HTTP.
    app()->terminate();

    return $emission;
}

it('entrega o próximo evento duas vezes com o mesmo event.id', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    new ArmScenario()->handle(WebhookOutcome::DuplicateNext);

    $emission = emitArmedWebhook();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), (string) $emission?->event_id));
});

it('assina o próximo evento com uma chave que o consumidor recusa', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    new ArmScenario()->handle(WebhookOutcome::CorruptSignatureNext);

    $emission = emitArmedWebhook();

    // O envelope é bem formado; só a assinatura não fecha com o PEM público que
    // o consumidor conhece — é exatamente o 401 que se quer exercitar lá.
    expect($this->verifyLikeConsumer($emission?->payload->rawBody ?? '', $emission?->signature))->toBeFalse();
});

it('represa o próximo evento sem POSTar nada', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    new ArmScenario()->handle(WebhookOutcome::HoldNext);

    $emission = emitArmedWebhook();

    Http::assertNothingSent();

    expect($emission?->isHeld())->toBeTrue()
        ->and($emission?->wasDelivered())->toBeFalse();
});

it('mantém a emissão represada fora do flush, que só recupera o que a rede engoliu', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    new ArmScenario()->handle(WebhookOutcome::HoldNext);
    emitArmedWebhook();

    expect(WebhookEmission::query()->pending()->count())->toBe(0)
        ->and(WebhookEmission::query()->held()->count())->toBe(1);
});

it('consome o armado na emissão e devolve a emissão seguinte ao neutro', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    new ArmScenario()->handle(WebhookOutcome::HoldNext);

    $represada = emitArmedWebhook();
    $normal = emitArmedWebhook();

    // A entrega pós-resposta relê a linha, então o objeto devolvido aqui é
    // anterior ao carimbo de `sent_at` — quem sabe do resultado é o banco.
    expect(ArmedScenario::query()->count())->toBe(0)
        ->and($represada?->isHeld())->toBeTrue()
        ->and($normal?->isHeld())->toBeFalse()
        ->and($normal?->refresh()->wasDelivered())->toBeTrue();
});

it('assina a emissão seguinte com a chave de verdade depois de um corrompimento armado', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    new ArmScenario()->handle(WebhookOutcome::CorruptSignatureNext);

    emitArmedWebhook();
    $normal = emitArmedWebhook();

    expect($this->verifyLikeConsumer($normal?->payload->rawBody ?? '', $normal?->signature))->toBeTrue();
});
