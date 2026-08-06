<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\Actions\AdvanceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankSubscription;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
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

    $fixture = $this->loadContractFixture('webhook/webhook_invoice_paid.json');

    // Vocabulário, não tipo: o consumidor faz `tryFrom()`/`match` sobre estes
    // três: `StarkbankEventType::triggersSettlement()` decide por `log.type` se
    // o Deposit concilia, e a key da entity dentro do log sai de `subscription`.
    // Comparar só `string` contra `string` deixaria passar um `credited` no
    // lugar de `paid` com a suíte verde e o consumidor mudo.
    $fixture['event']['subscription'] = $this->exactValue('invoice');
    $fixture['event']['log']['type'] = $this->exactValue('paid');
    $fixture['event']['log']['invoice']['status'] = $this->exactValue('paid');

    $this->assertMatchesRecordedShape($fixture, $emission?->payload->decoded() ?? []);
});

it('emite pelo caminho de produção o mesmo vocabulário do fixture', function (): void {
    // O teste acima passa o par subscription/eventType à mão, então nenhuma
    // troca em `InvoiceStatus::eventType()` o alcança. Este atravessa o avanço
    // lazy de verdade: é ele que quebra se `Paid` passar a emitir `credited`
    // (que a guarda exaustiva aceita, por estar no vocabulário do consumidor)
    // ou se a invoice passar a anunciar outra subscription.
    config(['fake-starkbank-invoice.advance_seconds' => 60]);

    $invoice = Invoice::factory()->create(['due' => CarbonImmutable::now()->addDay()]);

    $this->travel(61)->seconds();

    resolve(AdvanceInvoiceStatus::class)->handle($invoice);

    $emission = WebhookEmission::query()->firstOrFail();

    $fixture = $this->loadContractFixture('webhook/webhook_invoice_paid.json');
    $fixture['event']['subscription'] = $this->exactValue('invoice');
    $fixture['event']['log']['type'] = $this->exactValue('paid');
    $fixture['event']['log']['invoice']['status'] = $this->exactValue('paid');

    $this->assertMatchesRecordedShape($fixture, $emission->payload->decoded());
});

it('quebra quando o log.type deixa de ser o que dispara a conciliação do consumidor', function (): void {
    $fixture = $this->loadContractFixture('webhook/webhook_invoice_paid.json');
    $fixture['event']['log']['type'] = $this->exactValue('paid');

    $drifted = $this->loadContractFixture('webhook/webhook_invoice_paid.json');
    $drifted['event']['log']['type'] = 'credited';

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando a subscription anuncia outra perna', function (): void {
    $fixture = $this->loadContractFixture('webhook/webhook_invoice_paid.json');
    $fixture['event']['subscription'] = $this->exactValue('invoice');

    $drifted = $this->loadContractFixture('webhook/webhook_invoice_paid.json');
    $drifted['event']['subscription'] = 'brcode-payment';

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
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
