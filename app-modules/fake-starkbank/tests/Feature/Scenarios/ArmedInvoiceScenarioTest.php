<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| Perna assíncrona: invoice
|--------------------------------------------------------------------------
|
| O armado é consumido no POST e a invoice NASCE destinada. As leituras
| seguintes só executam o destino gravado na linha — nenhuma delas volta à
| tabela de cenários.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-invoice.advance_seconds' => 60]);
});

/**
 * @return array{invoices: list<array<string, mixed>>}
 */
function armedInvoicePayload(): array
{
    return ['invoices' => [[
        'amount' => 10_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'due' => CarbonImmutable::now()->addDay()->toIso8601String(),
        'expiration' => 3_600,
        'tags' => ['deposit-abc-123'],
    ]]];
}

function issueArmedInvoice(): string
{
    /** @var string $id */
    $id = test()->postSigned('/v2/invoice', armedInvoicePayload())->assertOk()->json('invoices.0.id');

    return $id;
}

it('faz a próxima invoice nascer destinada ao status armado e a entrega assim na releitura', function (InvoiceOutcome $outcome, InvoiceStatus $expected): void {
    new ArmScenario()->handle($outcome);

    $id = issueArmedInvoice();

    // O eco do POST ainda é `created`: o destino existe na linha e é a leitura
    // que o executa, como todo avanço deste fake.
    expect(Invoice::query()->findOrFail($id)->destined_status)->toBe($expected);

    $this->getSigned('/v2/invoice/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', $expected->value);
})->with([
    'overdue' => [InvoiceOutcome::Overdue, InvoiceStatus::Overdue],
    'expire' => [InvoiceOutcome::Expire, InvoiceStatus::Expired],
    'cancel' => [InvoiceOutcome::Cancel, InvoiceStatus::Canceled],
]);

it('consome o armado no POST e devolve a invoice seguinte ao neutro', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::Cancel);

    $armado = issueArmedInvoice();
    $neutro = issueArmedInvoice();

    expect(ArmedScenario::query()->count())->toBe(0)
        ->and(Invoice::query()->findOrFail($armado)->destined_status)->toBe(InvoiceStatus::Canceled)
        ->and(Invoice::query()->findOrFail($neutro)->destined_status)->toBeNull();

    $this->getSigned('/v2/invoice/'.$neutro, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', 'created');
});

it('zera o destino depois de aplicá-lo, para a invoice voltar a envelhecer normalmente', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::Overdue);

    $id = issueArmedInvoice();
    $this->getSigned('/v2/invoice/'.$id, $this->signedHeaders())->assertOk();

    expect(Invoice::query()->findOrFail($id)->destined_status)->toBeNull()
        ->and(Invoice::query()->findOrFail($id)->status)->toBe(InvoiceStatus::Overdue);
});

it('faz a invoice congelada nascer parada e nenhuma leitura mover o status', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::FreezeStatus);

    $id = issueArmedInvoice();

    expect(Invoice::query()->findOrFail($id)->frozen)->toBeTrue();

    // Mesmo muito depois do relógio de pagamento, a leitura não move nada.
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(30));

    $this->getSigned('/v2/invoice/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', 'created');

    CarbonImmutable::setTestNow();
});

it('soma os segundos armados ao relógio antes de a invoice virar paga', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::DelayPaid, new PixScenarioPayload(extraSeconds: 600));

    $id = issueArmedInvoice();

    expect(Invoice::query()->findOrFail($id)->extra_advance_seconds)->toBe(600);

    // 60s de relógio padrão já teriam pago; com +600s ainda não.
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(5));
    $this->getSigned('/v2/invoice/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', 'created');

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(10));
    $this->getSigned('/v2/invoice/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', 'paid');

    CarbonImmutable::setTestNow();
});

it('usa o atraso default quando o operador arma DelayPaid sem informar segundos', function (): void {
    new ArmScenario()->handle(InvoiceOutcome::DelayPaid);

    $id = issueArmedInvoice();

    expect(Invoice::query()->findOrFail($id)->extra_advance_seconds)->toBe(300);
});
