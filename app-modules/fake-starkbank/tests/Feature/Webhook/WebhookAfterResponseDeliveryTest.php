<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(SignsWebhooks::class);

/*
 * A prova do desenho pós-resposta: em dev o consumidor roda `php artisan serve`
 * single-thread, então POSTar o webhook de dentro do request em que ele está
 * chamando o fake trava os dois lados. A rota abaixo é a bancada — um endpoint
 * qualquer do fake que decide emitir no meio do próprio handler —, e o teste
 * observa a ordem: emissão gravada DURANTE o request, POST só DEPOIS.
 */

beforeEach(fn () => $this->configureFakeStarkbankWebhook());

it('grava a emissão durante o request e só POSTa depois da resposta ao consumidor', function (): void {
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);

    Route::middleware('api')->get('/__teste/emite-webhook', function (EmitWebhookEvent $emit): array {
        $emit->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, ['id' => '5155165527080960', 'amount' => 10_000]);

        // Ainda dentro do handler: a linha já existe e nenhum byte saiu na rede.
        expect(WebhookEmission::query()->count())->toBe(1);
        Http::assertNothingSent();

        return ['invoiceId' => '5155165527080960'];
    });

    $this->getJson('/__teste/emite-webhook')->assertOk()->assertJsonPath('invoiceId', '5155165527080960');

    // Depois que a resposta ao consumidor saiu, o POST acontece.
    Http::assertSentCount(1);

    $emission = WebhookEmission::query()->sole();

    expect($emission->sent_at)->not->toBeNull()
        ->and($emission->response_code)->toBe(200);
});

it('não deixa a falha da entrega pós-resposta contaminar a resposta do request original', function (): void {
    Http::fake([$this->webhookUrl() => Http::response('Unauthorized', 401)]);

    Route::middleware('api')->get('/__teste/emite-webhook-recusado', function (EmitWebhookEvent $emit): array {
        $emit->handle(StarkbankSubscription::Transfer, StarkbankEventType::Failed, ['id' => '777']);

        return ['transferId' => '777'];
    });

    $this->getJson('/__teste/emite-webhook-recusado')->assertOk();

    expect(WebhookEmission::query()->sole()->failed_reason)->toContain('HTTP 401');
});
