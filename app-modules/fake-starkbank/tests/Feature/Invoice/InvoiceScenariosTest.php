<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Invoice\Actions\AdvanceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Actions\ForceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Actions\SetInvoiceFrozen;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| Cenários da invoice
|--------------------------------------------------------------------------
|
| As duas Actions que o switchboard do painel vai embrulhar: forçar um estado
| ignorando o relógio e congelar a invoice. Aqui elas existem sozinhas — a UI
| chega no ticket de cenários armados.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook(url: null);
    config(['fake-starkbank-invoice.advance_seconds' => 60]);
});

it('força qualquer estado do vocabulário ignorando o relógio e anuncia o evento', function (InvoiceStatus $alvo): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $forcada = resolve(ForceInvoiceStatus::class)->handle($invoice, $alvo);

    expect($forcada->status)->toBe($alvo)
        ->and(WebhookEmission::query()->firstOrFail()->event_type)->toBe($alvo->eventType());
})->with([
    'cancelada' => [InvoiceStatus::Canceled],
    'paga' => [InvoiceStatus::Paid],
    'vencida' => [InvoiceStatus::Overdue],
    'expirada' => [InvoiceStatus::Expired],
    'estornada' => [InvoiceStatus::Reversed],
    'creditada' => [InvoiceStatus::Credited],
]);

it('paga na hora, encurtando a espera pelo avanço lazy', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $paga = resolve(ForceInvoiceStatus::class)->handle($invoice, InvoiceStatus::Paid);

    expect($paga->paid_at)->not->toBeNull()
        ->and(resolve(AdvanceInvoiceStatus::class)->handle($paga)->status)->toBe(InvoiceStatus::Paid);
});

it('grava o cancelamento em vez de mascará-lo: a leitura seguinte não descancela', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    resolve(ForceInvoiceStatus::class)->handle($invoice, InvoiceStatus::Canceled);

    $this->travel(1)->hour();

    expect(resolve(AdvanceInvoiceStatus::class)->handle($invoice->refresh())->status)
        ->toBe(InvoiceStatus::Canceled);
});

it('congela a invoice e a descongela, retomando o avanço de onde parou', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    resolve(SetInvoiceFrozen::class)->handle($invoice, frozen: true);

    $this->travel(61)->seconds();

    expect(resolve(AdvanceInvoiceStatus::class)->handle($invoice->refresh())->status)
        ->toBe(InvoiceStatus::Created);

    resolve(SetInvoiceFrozen::class)->handle($invoice, frozen: false);

    expect(resolve(AdvanceInvoiceStatus::class)->handle($invoice->refresh())->status)
        ->toBe(InvoiceStatus::Paid);
});
