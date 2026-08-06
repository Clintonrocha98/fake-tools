<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeStarkbank\Tests\Support\BuildsBrcodes;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use PHPUnit\Framework\ExpectationFailedException;

uses(SignsRequests::class, AssertsRecordedShape::class, BuildsBrcodes::class);

/*
 * Os quatro shapes da perna de BR Code contra os fixtures gravados do
 * consumidor: o preview (leitura de um código de terceiro) e os três do
 * pagamento — que, como na transfer, têm o MESMO conjunto de keys.
 */

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-brcode.advance_seconds' => 60]);

    $this->seed(DictEntrySeeder::class);
});

it('responde o preview no shape do fixture brcode_preview', function (): void {
    $response = $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->staticBrcode(amount: '250.00')),
        $this->signedHeaders(),
    );

    $response->assertOk();

    $fixture = $this->loadContractFixture('brcode/brcode_preview.json');

    // `status` e `allowChange` são literais: o consumidor decide por eles, e
    // comparar só o tipo deixaria passar qualquer palavra ou qualquer booleano.
    $fixture['previews'][0]['status'] = $this->exactValue('active');
    $fixture['previews'][0]['allowChange'] = $this->exactValue(false);

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('responde o pagamento no shape do fixture brcode_payment_created', function (): void {
    $response = $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => $this->staticBrcode(amount: '250.00'),
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
    ]]]);

    $response->assertOk();

    $fixture = $this->loadContractFixture('brcode/brcode_payment_created.json');
    $fixture['payments'][0]['status'] = $this->exactValue('created');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('responde a releitura no shape do fixture brcode_payment_settled', function (): void {
    $pagamento = BrcodePayment::factory()->settled()->create(['tags' => ['conversion-abc-123']]);

    $response = $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders());

    $response->assertOk();

    $fixture = $this->loadContractFixture('brcode/brcode_payment_settled.json');
    $fixture['payment']['status'] = $this->exactValue('success');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('responde o extrato no shape do fixture brcode_payment_list_settled', function (): void {
    BrcodePayment::factory()->settled()->create(['tags' => ['conversion-abc-123']]);

    $response = $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders());

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('brcode/brcode_payment_list_settled.json'),
        (array) $response->json(),
    );

    $response->assertJsonPath('cursor', null);
});

it('serve o mesmo conjunto de keys no eco, na releitura e na lista', function (): void {
    $doPost = (array) $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => $this->staticBrcode(),
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
    ]]])->assertOk()->json('payments.0');

    $id = (string) $doPost['id'];

    $doGet = (array) $this->getSigned('/v2/brcode-payment/'.$id, $this->signedHeaders())->assertOk()->json('payment');
    $daLista = (array) $this->getSigned('/v2/brcode-payment', $this->signedHeaders())->assertOk()->json('payments.0');

    expect(array_keys($doGet))->toEqualCanonicalizing(array_keys($doPost))
        ->and(array_keys($daLista))->toEqualCanonicalizing(array_keys($doPost));
});

it('nunca produz a forma que o consumidor lê como malformed', function (): void {
    // Um 200 com `payments` vazio ou sem `id` vira StarkbankRequestFailed::malformed()
    // do lado de lá — e uma Conversion que nunca sela o funding.
    $response = $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => $this->staticBrcode(),
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'description' => 'BRD funding conversion-abc-123',
    ]]])->assertOk();

    expect($response->json('payments'))->toBeArray()->not->toBeEmpty()
        ->and((string) $response->json('payments.0.id'))->not->toBeEmpty();
});

it('serve datas que o consumidor materializa em Carbon', function (): void {
    $pagamento = BrcodePayment::factory()->settled()->create();

    $lido = (array) $this->getSigned('/v2/brcode-payment/'.$pagamento->id, $this->signedHeaders())->assertOk()->json('payment');

    foreach (['created', 'updated'] as $campo) {
        expect(CarbonImmutable::parse((string) $lido[$campo])->toIso8601String())->toBeString();
    }
});

it('devolve o envelope de erro do StarkBank num id desconhecido, nunca HTML', function (): void {
    $response = $this->getSigned('/v2/brcode-payment/5824000009469952', $this->signedHeaders());

    $response->assertStatus(404)
        ->assertHeader('content-type', 'application/json');

    $fixture = $this->loadContractFixture('errors/error_envelope.json');
    $fixture['errors'][0]['code'] = $this->exactValue('invalidId');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('devolve o envelope de erro na recusa de um BR Code ilegível', function (): void {
    $response = $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($this->tamperedBrcode($this->staticBrcode())),
        $this->signedHeaders(),
    );

    $response->assertStatus(400);

    $fixture = $this->loadContractFixture('errors/error_envelope.json');
    $fixture['errors'][0]['code'] = $this->exactValue('invalidBrcode');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('devolve o envelope de erro na recusa do externalId, com a mensagem literal do provedor', function (): void {
    // O consumidor conta com esta mensagem específica para não voltar a mandar
    // `externalId`: a correlação de brcode-payment viaja só em `tags`.
    $response = $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => $this->staticBrcode(),
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
        'externalId' => 'conversion-abc-123',
    ]]]);

    $response->assertStatus(400);

    $fixture = $this->loadContractFixture('errors/error_envelope.json');
    $fixture['errors'][0]['code'] = $this->exactValue('invalidJson');
    $fixture['errors'][0]['message'] = $this->exactValue('Unknown parameters in payment: externalId');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('devolve o envelope de erro na recusa do BR Code dinâmico sem description', function (): void {
    // Só o dinâmico exige `description` — o estático não, porque o próprio
    // brcode já carrega o valor a pagar.
    $response = $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => $this->dynamicBrcode(),
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
    ]]]);

    $response->assertStatus(400);

    $fixture = $this->loadContractFixture('errors/error_envelope.json');
    $fixture['errors'][0]['code'] = $this->exactValue('invalidJson');
    $fixture['errors'][0]['message'] = $this->exactValue('Missing parameters in payment: description');

    $this->assertMatchesRecordedShape($fixture, (array) $response->json());
});

it('quebra quando o amount do preview deixa de ser inteiro de centavos', function (): void {
    // Prova do mecanismo: sem isto, a suíte de contrato é teatro.
    $fixture = $this->loadContractFixture('brcode/brcode_preview.json');

    $drifted = $fixture;
    $drifted['previews'][0]['amount'] = '250.00';

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('quebra quando o taxId some do preview, porque é dele que sai o guard de destino', function (): void {
    $fixture = $this->loadContractFixture('brcode/brcode_preview.json');

    $drifted = $fixture;
    unset($drifted['previews'][0]['taxId']);

    expect(fn () => $this->assertMatchesRecordedShape($fixture, $drifted))
        ->toThrow(ExpectationFailedException::class);
});
