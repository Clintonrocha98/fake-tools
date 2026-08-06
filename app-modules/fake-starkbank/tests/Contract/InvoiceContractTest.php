<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use PHPUnit\Framework\ExpectationFailedException;

uses(SignsRequests::class, AssertsRecordedShape::class);

/*
 * Os três shapes de invoice contra os fixtures gravados do consumidor: o eco
 * GORDO da emissão, o shape MAGRO da releitura e o item da listagem. A
 * assimetria entre o POST e o GET é do StarkBank real — servir o gordo na
 * releitura faria o fake aceitar um consumidor que o provedor quebraria.
 */

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-invoice.advance_seconds' => 60]);
});

it('responde a emissão no shape do fixture invoice_issued', function (): void {
    $response = $this->postSigned('/v2/invoice', ['invoices' => [[
        'amount' => 10_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'due' => CarbonImmutable::now()->addMinutes(15)->toIso8601String(),
        'expiration' => 0,
        'tags' => ['deposit-abc-123'],
    ]]]);

    $response->assertOk();

    $fixture = $this->loadContractFixture('invoice/invoice_issued.json');

    // A mensagem de sucesso o consumidor não lê, mas é literal na wire: comparar
    // só o tipo aqui deixaria passar qualquer string.
    $fixture['message'] = $this->exactValue('Invoice successfully created');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('responde a releitura no shape do fixture invoice_paid', function (): void {
    $invoice = Invoice::factory()->paid()->create(['tags' => ['deposit-abc-123']]);

    $response = $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('invoice/invoice_paid.json'),
        (array) $response->json(),
    );
});

it('responde o extrato no shape do fixture invoice_list_paid', function (): void {
    Invoice::factory()->paid()->create(['tags' => ['deposit-abc-123']]);

    $response = $this->getSigned('/v2/invoice?status=paid', $this->signedHeaders());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('invoice/invoice_list_paid.json'),
        (array) $response->json(),
    );

    $response->assertJsonPath('cursor', null);
});

it('serve na releitura só o subconjunto magro, sem os campos do eco de emissão', function (): void {
    $invoice = Invoice::factory()->paid()->create();

    $lido = (array) $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders())->assertOk()->json('invoice');
    $emitido = $this->loadContractFixture('invoice/invoice_issued.json');

    /** @var array<string, mixed> $shapeGordo */
    $shapeGordo = $emitido['invoices'][0];

    $soDoPost = array_diff(array_keys($shapeGordo), array_keys($lido));

    expect($soDoPost)->not->toBeEmpty()
        ->and(array_values($soDoPost))->toEqualCanonicalizing([
            'nominalAmount', 'fee', 'fine', 'fineAmount', 'interest', 'interestAmount',
            'discountAmount', 'discounts', 'descriptions', 'displayDescription',
            'reversalDisplayDescription', 'rules', 'splits', 'metadata', 'transactionIds',
            'link', 'pdf',
        ]);
});

it('serve datas que o consumidor materializa em Carbon', function (): void {
    // InvoiceResponse::resolveExpiresAt() parseia `due` (ou `created` + `expiration`);
    // uma string que não parseia derruba o OpenDeposit do lado de lá.
    $invoice = Invoice::factory()->create();

    $lido = (array) $this->getSigned('/v2/invoice/'.$invoice->id, $this->signedHeaders())->assertOk()->json('invoice');

    foreach (['created', 'due', 'updated'] as $campo) {
        expect(CarbonImmutable::parse((string) $lido[$campo])->toIso8601String())->toBeString();
    }
});

it('devolve o envelope de erro do StarkBank num id desconhecido, nunca HTML', function (): void {
    $response = $this->getSigned('/v2/invoice/5155165527080960', $this->signedHeaders());

    $response->assertStatus(404)
        ->assertHeader('content-type', 'application/json');

    $fixture = $this->loadContractFixture('errors/error_envelope.json');
    $fixture['errors'][0]['code'] = $this->exactValue('invalidId');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('quebra quando uma key documentada some da releitura', function (): void {
    // Prova do mecanismo: sem isto, a suíte de contrato é teatro.
    $fixture = $this->loadContractFixture('invoice/invoice_paid.json');

    $drifted = $fixture;
    unset($drifted['invoice']['brcode']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando o amount deixa de ser inteiro de centavos', function (): void {
    $fixture = $this->loadContractFixture('invoice/invoice_paid.json');

    $drifted = $fixture;
    $drifted['invoice']['amount'] = '100.00';

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando uma key documentada é renomeada para snake_case', function (): void {
    $fixture = $this->loadContractFixture('invoice/invoice_paid.json');

    $drifted = $fixture;
    $drifted['invoice']['tax_id'] = $drifted['invoice']['taxId'];
    unset($drifted['invoice']['taxId']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});
