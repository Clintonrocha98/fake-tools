<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\ExpectationFailedException;

uses(SignsWebhooks::class, AssertsRecordedShape::class);

/*
 * O shape do envelope que SAI do fake, contra o fixture gravado do consumidor.
 * Único fixture do sentido fake → consumidor: aqui o fake é quem monta o corpo,
 * e `WebhookEvent::fromWebhookBody()` do outro lado o lê posicionalmente.
 */

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook();
    Http::fake([$this->webhookUrl() => Http::response(['status' => 'ok'])]);
    config(['fake-starkbank.workspace.id' => '6341320293482496']);
});

it('emite no shape do fixture webhook_invoice_paid do consumidor', function (): void {
    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('webhook/webhook_invoice_paid.json'),
        $emission?->payload->decoded() ?? [],
    );
});

it('carrega exatamente as keys camelCase do envelope, sem nenhuma em snake_case', function (): void {
    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $event = $emission?->payload->decoded()['event'] ?? [];

    expect(array_keys($event))->toEqualCanonicalizing(['id', 'created', 'workspaceId', 'subscription', 'log'])
        ->and(array_keys($event['log']))->toEqualCanonicalizing(['id', 'created', 'type', 'invoice']);
});

it('serve created num formato que o consumidor parseia como data', function (): void {
    // WebhookEvent::parseTime() cai em `now()` quando não parseia, e uma data
    // silenciosamente errada é pior que uma entrega recusada.
    $emission = resolve(EmitWebhookEvent::class)
        ->handle(StarkbankSubscription::Invoice, StarkbankEventType::Paid, $this->invoiceEntity());

    $event = $emission?->payload->decoded()['event'] ?? [];

    expect($event['created'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/')
        ->and($event['log']['created'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/')
        ->and(CarbonImmutable::parse((string) $event['created'])->toIso8601String())->toBeString();
});

it('quebra quando a entity de brcode-payment vai para a key errada do log', function (): void {
    // Prova do mecanismo: a assimetria subscription/key é a armadilha do wire.
    $fixture = $this->loadContractFixture('webhook/webhook_invoice_paid.json');

    $drifted = $fixture;
    $drifted['event']['log']['brcode-payment'] = $drifted['event']['log']['invoice'];
    unset($drifted['event']['log']['invoice']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando workspaceId é renomeado para snake_case', function (): void {
    $fixture = $this->loadContractFixture('webhook/webhook_invoice_paid.json');

    $drifted = $fixture;
    $drifted['event']['workspace_id'] = $drifted['event']['workspaceId'];
    unset($drifted['event']['workspaceId']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});
