<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\ReleaseEmissionHold;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| Liberação de uma emissão represada
|--------------------------------------------------------------------------
|
| O desfecho `HoldNext` consome o cenário na hora da emissão, mas quem carrega a
| espera é a linha. Liberar entrega os bytes represados como estavam.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook();
});

it('entrega os bytes represados e limpa a retenção', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = WebhookEmission::factory()->held()->create(['url' => $this->webhookUrl()]);

    $released = resolve(ReleaseEmissionHold::class)($emission);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->body() === $emission->payload->rawBody
        && $request->header('Digital-Signature')[0] === $emission->signature);

    expect($released->isHeld())->toBeFalse()
        ->and($released->wasDelivered())->toBeTrue();
});

it('preserva o event.id do instante do evento, não o da liberação', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = WebhookEmission::factory()->held()->create(['url' => $this->webhookUrl()]);
    $eventId = $emission->event_id;

    resolve(ReleaseEmissionHold::class)($emission);

    expect($emission->refresh()->event_id)->toBe($eventId);
});

it('ignora a liberação de uma emissão que não estava represada', function (): void {
    // Reentregar aqui seria um replay disfarçado — quem quer isso usa o replay.
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    $emission = WebhookEmission::factory()->delivered()->create(['url' => $this->webhookUrl()]);

    resolve(ReleaseEmissionHold::class)($emission);

    Http::assertNothingSent();
});

it('deixa a emissão liberada pendente quando o consumidor recusa', function (): void {
    Http::fake([$this->webhookUrl() => Http::response('não autorizado', 401)]);

    $emission = WebhookEmission::factory()->held()->create(['url' => $this->webhookUrl()]);

    $released = resolve(ReleaseEmissionHold::class)($emission);

    expect($released->isHeld())->toBeFalse()
        ->and($released->wasDelivered())->toBeFalse()
        ->and($released->response_code)->toBe(401);
});
