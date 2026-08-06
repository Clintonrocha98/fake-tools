<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\Enums\InvoiceStatus;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class, SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| POST /v2/invoice
|--------------------------------------------------------------------------
|
| A emissão da cobrança PIX: envelope plural na entrada e na saída, shape gordo
| no eco e o brcode já anexado — é ele que o consumidor devolve ao cliente.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{invoices: list<array<string, mixed>>}
 */
function issuePayload(array $overrides = []): array
{
    return ['invoices' => [array_merge([
        'amount' => 10_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'due' => CarbonImmutable::now()->addHour()->toIso8601String(),
        'expiration' => 0,
        'tags' => ['deposit-abc-123'],
    ], $overrides)]];
}

it('emite a cobrança no envelope plural com a mensagem de sucesso do StarkBank', function (): void {
    $response = $this->postSigned('/v2/invoice', issuePayload());

    $response->assertOk()
        ->assertJsonPath('message', 'Invoice successfully created')
        ->assertJsonCount(1, 'invoices')
        ->assertJsonPath('invoices.0.amount', 10_000)
        ->assertJsonPath('invoices.0.status', 'created')
        ->assertJsonPath('invoices.0.name', 'Ada Lovelace')
        ->assertJsonPath('invoices.0.taxId', '012.345.678-90')
        ->assertJsonPath('invoices.0.tags', ['deposit-abc-123']);
});

it('grava a invoice em created com o correlationId na primeira tag', function (): void {
    $id = (string) $this->postSigned('/v2/invoice', issuePayload())->assertOk()->json('invoices.0.id');

    $invoice = Invoice::query()->findOrFail($id);

    expect($invoice->status)->toBe(InvoiceStatus::Created)
        ->and($invoice->tags->correlationId())->toBe('deposit-abc-123')
        ->and($invoice->paid_at)->toBeNull()
        ->and($invoice->frozen)->toBeFalse();
});

it('gera um id numérico de 16 dígitos e o embute no brcode, no link e no pdf', function (): void {
    $invoice = (array) $this->postSigned('/v2/invoice', issuePayload())->assertOk()->json('invoices.0');

    $id = (string) $invoice['id'];

    expect($id)->toMatch('/^\d{16}$/')
        ->and($invoice['brcode'])->toContain('brcode-h.starkbank.com/v2/'.$id)
        ->and($invoice['link'])->toContain($id)
        ->and($invoice['pdf'])->toContain($id.'.pdf');
});

it('ecoa o shape gordo da emissão, com os campos que o fake não modela como constantes', function (): void {
    $invoice = (array) $this->postSigned('/v2/invoice', issuePayload())->assertOk()->json('invoices.0');

    expect($invoice)->toMatchArray([
        'nominalAmount' => 10_000,
        'fee' => 0,
        'fine' => 2,
        'fineAmount' => 0,
        'interest' => 1,
        'interestAmount' => 0,
        'discountAmount' => 0,
        'discounts' => [],
        'descriptions' => [],
        'displayDescription' => '',
        'reversalDisplayDescription' => '',
        'rules' => [],
        'splits' => [],
        'metadata' => [],
        'transactionIds' => [],
    ]);
});

it('aceita N invoices no array, mesmo que o consumidor mande sempre uma', function (): void {
    $payload = ['invoices' => [
        issuePayload(['tags' => ['deposit-1']])['invoices'][0],
        issuePayload(['amount' => 25_000, 'tags' => ['deposit-2']])['invoices'][0],
    ]];

    $response = $this->postSigned('/v2/invoice', $payload);

    $response->assertOk()
        ->assertJsonCount(2, 'invoices')
        ->assertJsonPath('invoices.1.amount', 25_000)
        ->assertJsonPath('invoices.1.tags', ['deposit-2']);

    expect(Invoice::query()->count())->toBe(2);
});

it('assume o vencimento de dois dias da doc quando o consumidor omite o due', function (): void {
    $this->freezeTime();

    $payload = issuePayload();
    unset($payload['invoices'][0]['due']);

    $id = (string) $this->postSigned('/v2/invoice', $payload)->assertOk()->json('invoices.0.id');

    expect(Invoice::query()->findOrFail($id)->due->toIso8601String())
        ->toBe(CarbonImmutable::now()->addDays(2)->toIso8601String());
});

it('anuncia o evento created na emissão', function (): void {
    $this->app->forgetInstance(EmitsWebhookEvents::class);
    $this->app->bind(EmitsWebhookEvents::class, EmitWebhookEvent::class);
    $this->configureFakeStarkbankWebhook(url: null);

    $id = (string) $this->postSigned('/v2/invoice', issuePayload())->assertOk()->json('invoices.0.id');

    $emission = WebhookEmission::query()->firstOrFail();

    expect($emission->event_type)->toBe(StarkbankEventType::Created)
        ->and($emission->entity_id)->toBe($id)
        ->and($emission->payload->decoded())
        ->toHaveKey('event.log.invoice.brcode');
});

it('recusa a emissão inválida no envelope de erro do StarkBank, nunca no 422 do Laravel', function (array $override, string $trecho): void {
    $payload = issuePayload();
    $payload['invoices'][0] = array_merge($payload['invoices'][0], $override);

    $response = $this->postSigned('/v2/invoice', $payload);

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');

    expect((string) $response->json('errors.0.message'))->toContain($trecho);

    expect(Invoice::query()->count())->toBe(0);
})->with([
    'amount ausente' => [['amount' => null], 'amount'],
    'amount zerado' => [['amount' => 0], 'amount'],
    'nome ausente' => [['name' => null], 'name'],
    'taxId ausente' => [['taxId' => null], 'taxId'],
    'due que não é data' => [['due' => 'ontem'], 'due'],
]);

it('recusa o envelope sem o array invoices', function (): void {
    $this->postSigned('/v2/invoice', ['invoices' => []])
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});

it('assina sobre o corpo: um body trocado depois de assinado não autentica', function (): void {
    $assinado = $this->jsonBody(issuePayload());
    $headers = $this->signedHeaders($assinado);

    // Mesmos headers, outro corpo — é exatamente o que um replay adulterado faz.
    $this->postSigned('/v2/invoice', issuePayload(['amount' => 999_999]), $headers)
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'invalidSignature');

    expect(Invoice::query()->count())->toBe(0);
});
