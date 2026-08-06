<?php

declare(strict_types=1);

use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Tests\Support\BuildsBrcodes;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class, BuildsBrcodes::class);

/*
|--------------------------------------------------------------------------
| POST /v2/brcode-payment
|--------------------------------------------------------------------------
|
| O pagamento do funding: envelope plural na entrada e na saída, e as quatro
| recusas literais que o consumidor já apanhou ao vivo do provedor.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-brcode.advance_seconds' => 60]);

    $this->seed(DictEntrySeeder::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array{payments: list<array<string, mixed>>}
 */
function paymentPayload(string $brcode, array $overrides = []): array
{
    return ['payments' => [array_merge([
        'brcode' => $brcode,
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
    ], $overrides)]];
}

it('paga o BR Code no envelope plural com o id e o status de partida', function (): void {
    $brcode = $this->staticBrcode(amount: '250.00');

    $response = $this->postSigned('/v2/brcode-payment', paymentPayload($brcode));

    $response->assertOk()
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('payments.0.brcode', $brcode)
        ->assertJsonPath('payments.0.taxId', '20.018.183/0001-80')
        ->assertJsonPath('payments.0.amount', 25_000)
        ->assertJsonPath('payments.0.status', 'created')
        ->assertJsonPath('payments.0.tags', ['conversion-abc-123']);

    expect((string) $response->json('payments.0.id'))->toMatch('/^\d{16}$/');
});

it('nunca ecoa a description, que é parâmetro de ida', function (): void {
    $pagamento = (array) $this->postSigned('/v2/brcode-payment', paymentPayload($this->staticBrcode()))
        ->assertOk()
        ->json('payments.0');

    expect(array_keys($pagamento))->toEqualCanonicalizing([
        'id', 'brcode', 'taxId', 'amount', 'status', 'tags', 'created', 'updated',
    ]);
});

it('grava o código verbatim e o correlationId na primeira tag', function (): void {
    $brcode = $this->staticBrcode();

    $id = (string) $this->postSigned('/v2/brcode-payment', paymentPayload($brcode))->assertOk()->json('payments.0.id');

    $pagamento = BrcodePayment::query()->findOrFail($id);

    expect($pagamento->status)->toBe(BrcodePaymentStatus::Created)
        ->and($pagamento->brcode)->toBe($brcode)
        ->and($pagamento->description)->toBe('BRD funding conversion-abc-123')
        ->and($pagamento->tags->correlationId())->toBe('conversion-abc-123');
});

it('recusa o externalId com a mensagem literal do provedor', function (): void {
    $response = $this->postSigned('/v2/brcode-payment', paymentPayload($this->staticBrcode(), [
        'externalId' => 'conversion-abc-123',
    ]));

    $response->assertStatus(400)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidJson', 'message' => 'Unknown parameters in payment: externalId'],
            ],
        ]);

    expect(BrcodePayment::query()->count())->toBe(0);
});

it('recusa o BR Code dinâmico sem description com a mensagem literal do provedor', function (): void {
    $payload = paymentPayload($this->dynamicBrcode());
    unset($payload['payments'][0]['description']);

    $response = $this->postSigned('/v2/brcode-payment', $payload);

    $response->assertStatus(400)
        ->assertExactJson([
            'errors' => [
                ['code' => 'invalidJson', 'message' => 'Missing parameters in payment: description'],
            ],
        ]);

    expect(BrcodePayment::query()->count())->toBe(0);
});

it('aceita o BR Code estático sem description, porque só o dinâmico a exige', function (): void {
    $payload = paymentPayload($this->staticBrcode());
    unset($payload['payments'][0]['description']);

    $this->postSigned('/v2/brcode-payment', $payload)->assertOk();

    expect(BrcodePayment::query()->firstOrFail()->description)->toBeNull();
});

it('recusa um taxId que não é o titular da chave embutida no código', function (): void {
    $response = $this->postSigned('/v2/brcode-payment', paymentPayload($this->staticBrcode(), [
        'taxId' => '012.345.678-90',
    ]));

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidTaxId');

    expect((string) $response->json('errors.0.message'))->toContain('does not match the brcode receiver')
        ->and(BrcodePayment::query()->count())->toBe(0);
});

it('aceita o mesmo CNPJ com pontuação diferente, porque dígitos são dígitos', function (): void {
    $this->postSigned('/v2/brcode-payment', paymentPayload($this->staticBrcode(), [
        'taxId' => '20018183000180',
    ]))->assertOk();

    expect(BrcodePayment::query()->count())->toBe(1);
});

it('recusa um amount diferente do campo 54 do código estático', function (): void {
    $response = $this->postSigned('/v2/brcode-payment', paymentPayload($this->staticBrcode(amount: '250.00'), [
        'amount' => 24_999,
    ]));

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidAmount');

    expect((string) $response->json('errors.0.message'))->toContain('does not match the brcode amount')
        ->and(BrcodePayment::query()->count())->toBe(0);
});

it('aceita qualquer amount no BR Code dinâmico, que é o que allowChange anuncia', function (): void {
    $this->postSigned('/v2/brcode-payment', paymentPayload($this->dynamicBrcode(), ['amount' => 99_999]))
        ->assertOk()
        ->assertJsonPath('payments.0.amount', 99_999);
});

it('recusa um código que não decodifica com invalidBrcode', function (): void {
    $response = $this->postSigned('/v2/brcode-payment', paymentPayload($this->tamperedBrcode($this->staticBrcode())));

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidBrcode');

    expect(BrcodePayment::query()->count())->toBe(0);
});

it('paga sem conferir o recebedor quando a chave do código está fora do DICT', function (): void {
    // O fake não tem contra o que comparar o taxId; o fail-closed desse caminho
    // é do consumidor, que já viu `taxId: ""` no preview e recusou antes daqui.
    $this->postSigned('/v2/brcode-payment', paymentPayload(
        $this->staticBrcode(pixKey: 'ninguem@brd.digital'),
        ['taxId' => '999.999.999-99'],
    ))->assertOk();

    expect(BrcodePayment::query()->count())->toBe(1);
});

it('recusa o pagamento inválido no envelope de erro do StarkBank, nunca no 422 do Laravel', function (array $override, string $trecho): void {
    $response = $this->postSigned('/v2/brcode-payment', paymentPayload($this->staticBrcode(), $override));

    $response->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');

    expect((string) $response->json('errors.0.message'))->toContain($trecho)
        ->and(BrcodePayment::query()->count())->toBe(0);
})->with([
    'brcode ausente' => [['brcode' => null], 'brcode'],
    'taxId ausente' => [['taxId' => null], 'taxId'],
    'amount ausente' => [['amount' => null], 'amount'],
    'amount zerado' => [['amount' => 0], 'amount'],
]);

it('recusa o envelope sem o array payments', function (): void {
    $this->postSigned('/v2/brcode-payment', ['payments' => []])
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidRequest');
});

it('não cria nada do lote quando um item é recusado', function (): void {
    $legitimo = paymentPayload($this->staticBrcode())['payments'][0];
    $recusado = paymentPayload($this->staticBrcode(), ['amount' => 1])['payments'][0];

    $this->postSigned('/v2/brcode-payment', ['payments' => [$legitimo, $recusado]])
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'invalidAmount');

    expect(BrcodePayment::query()->count())->toBe(0);
});

it('cria pagamentos distintos em dois POST iguais, porque esta perna não tem chave de idempotência', function (): void {
    // O endpoint recusa `externalId` e `tags` não deduplica no provedor: quem
    // impede o segundo funding é o guard de estado da Conversion, do lado de lá.
    $payload = paymentPayload($this->staticBrcode());

    $this->postSigned('/v2/brcode-payment', $payload)->assertOk();
    $this->postSigned('/v2/brcode-payment', $payload)->assertOk();

    expect(BrcodePayment::query()->count())->toBe(2);
});

it('assina sobre o corpo: um body trocado depois de assinado não autentica', function (): void {
    $brcode = $this->staticBrcode();
    $headers = $this->signedHeaders($this->jsonBody(paymentPayload($brcode)));

    $this->postSigned('/v2/brcode-payment', paymentPayload($brcode, ['amount' => 999_999]), $headers)
        ->assertStatus(401)
        ->assertJsonPath('errors.0.code', 'invalidSignature');

    expect(BrcodePayment::query()->count())->toBe(0);
});
