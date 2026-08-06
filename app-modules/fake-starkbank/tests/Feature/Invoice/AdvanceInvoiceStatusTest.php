<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Invoice\Actions\AdvanceInvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| Avanço lazy da invoice
|--------------------------------------------------------------------------
|
| Dois relógios: o do documento (`due` + graça) e o do pagamento simulado
| (`advance_seconds`). O primeiro tem precedência — uma invoice lida depois de
| vencida nunca "paga atrasado".
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankWebhook(url: null);
    config(['fake-starkbank-invoice.advance_seconds' => 60]);
});

function avanca(Invoice $invoice): Invoice
{
    return resolve(AdvanceInvoiceStatus::class)->handle($invoice);
}

it('não paga antes de advance_seconds', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    expect(avanca($invoice)->status)->toBe(InvoiceStatus::Created)
        ->and(WebhookEmission::query()->count())->toBe(0);
});

it('paga a invoice madura, carimba paid_at e anuncia o evento paid', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $this->travel(61)->seconds();

    $avancada = avanca($invoice);

    expect($avancada->status)->toBe(InvoiceStatus::Paid)
        ->and($avancada->paid_at)->not->toBeNull();

    $emission = WebhookEmission::query()->firstOrFail();

    expect($emission->event_type)->toBe(StarkbankEventType::Paid)
        ->and($emission->entity_id)->toBe($invoice->id);
});

it('não reemite o evento numa releitura de invoice que já avançou', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->addDay()]);

    $this->travel(61)->seconds();

    avanca($invoice);
    avanca($invoice->refresh());
    avanca($invoice->refresh());

    expect(WebhookEmission::query()->count())->toBe(1);
});

it('vence para overdue enquanto houver graça e expira quando ela esgota', function (): void {
    $invoice = Invoice::factory()->create([
        'due' => now()->subMinute(),
        'expiration' => 3_600,
    ]);

    expect(avanca($invoice)->status)->toBe(InvoiceStatus::Overdue)
        ->and($invoice->refresh()->expired_at)->toBeNull();

    $this->travel(3_601)->seconds();

    $expirada = avanca($invoice->refresh());

    expect($expirada->status)->toBe(InvoiceStatus::Expired)
        ->and($expirada->expired_at)->not->toBeNull()
        ->and(WebhookEmission::query()->oldest()->pluck('event_type')->all())
        ->toBe([StarkbankEventType::Overdue, StarkbankEventType::Expired]);
});

it('expira direto quando o vencimento não tem graça nenhuma', function (): void {
    $invoice = Invoice::factory()->create(['due' => now()->subSecond(), 'expiration' => 0]);

    expect(avanca($invoice)->status)->toBe(InvoiceStatus::Expired);

    expect(WebhookEmission::query()->firstOrFail()->event_type)->toBe(StarkbankEventType::Expired);
});

it('deixa o vencimento vencer o pagamento simulado: invoice vencida nunca paga atrasado', function (): void {
    // Os dois relógios já dispararam nesta leitura; o do documento manda.
    $invoice = Invoice::factory()->create(['due' => now()->addSeconds(30), 'expiration' => 0]);

    $this->travel(120)->seconds();

    expect(avanca($invoice)->status)->toBe(InvoiceStatus::Expired);
});

it('nunca mexe num status terminal', function (InvoiceStatus $status): void {
    $invoice = Invoice::factory()->create(['status' => $status, 'due' => now()->subDay()]);

    expect(avanca($invoice)->status)->toBe($status)
        ->and(WebhookEmission::query()->count())->toBe(0);
})->with([
    'paga' => [InvoiceStatus::Paid],
    'expirada' => [InvoiceStatus::Expired],
    'cancelada' => [InvoiceStatus::Canceled],
    'estornada' => [InvoiceStatus::Reversed],
    'creditada' => [InvoiceStatus::Credited],
]);

it('não avança invoice congelada, nem pelo pagamento nem pelo vencimento', function (): void {
    $invoice = Invoice::factory()->frozen()->create(['due' => now()->subDay()]);

    $this->travel(3_600)->seconds();

    expect(avanca($invoice)->status)->toBe(InvoiceStatus::Created)
        ->and(WebhookEmission::query()->count())->toBe(0);
});

it('com advance_seconds desligado, não paga sozinha mas o vencimento continua valendo', function (): void {
    config(['fake-starkbank-invoice.advance_seconds' => 0]);

    $emAberto = Invoice::factory()->create(['due' => now()->addDay()]);
    $vencida = Invoice::factory()->create(['due' => now()->subSecond(), 'expiration' => 0]);

    $this->travel(600)->seconds();

    expect(avanca($emAberto)->status)->toBe(InvoiceStatus::Created)
        ->and(avanca($vencida)->status)->toBe(InvoiceStatus::Expired);
});
