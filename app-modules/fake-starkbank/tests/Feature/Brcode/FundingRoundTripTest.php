<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Actions\BuildStaticBrcode;
use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Tests\Support\SignsWebhooks;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

uses(SignsRequests::class, SignsWebhooks::class);

/*
|--------------------------------------------------------------------------
| O funding cross-fake, ponta a ponta
|--------------------------------------------------------------------------
|
| O ÚNICO ponto em que os dois fakes se encontram — e é aqui, num teste, nunca
| em runtime (ADR-0001). O BR Code é montado pelo emissor de verdade do
| fake-binance e decodificado pelo decodificador de verdade daqui: se as duas
| pontas do contrato (a chave PIX de env e a variante do CRC16) divergirem, este
| teste é quem denuncia, e não o operador em dev.
|
| O roteiro é o do SendConversionFunding: previsualizar → conferir os dois
| guards → pagar → deixar o relógio correr → varrer o extrato.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->configureFakeStarkbankWebhook(url: null);
    config([
        'fake-starkbank-brcode.advance_seconds' => 60,
        // As duas metades do contrato de env, replicadas como em dev.
        'fake-binance-fiat.pix_key' => 'funding@fake-binance.dev',
        'fake-starkbank-dict.funding.pix_key' => 'funding@fake-binance.dev',
        'fake-starkbank-dict.funding.tax_id' => '20.018.183/0001-80',
    ]);

    $this->seed(DictEntrySeeder::class);
});

/**
 * O default CRU escrito no arquivo de config — o literal do segundo argumento de
 * `env()`. Lido do FONTE de propósito: `config()` já resolveu e o `beforeEach`
 * acima sobrescreve as duas metades para exercitar o CRC16, enquanto resolver
 * `env()` de novo devolveria o que o `.env` do dev disser. O que precisa casar
 * entre os dois fakes é o default combinado, não o valor de uma máquina.
 */
function envDefaultLiteral(string $configFile, string $envKey): string
{
    $matched = preg_match(
        sprintf("/env\(\s*'%s'\s*,\s*'([^']*)'\s*\)/", preg_quote($envKey, '/')),
        (string) file_get_contents($configFile),
        $matches,
    );

    expect($matched)->toBe(1, sprintf('Nenhum default literal de %s em %s.', $envKey, $configFile));

    return $matches[1];
}

it('mantém idênticos os defaults das duas metades do contrato cross-fake', function (): void {
    // O contrato entre os dois fakes é uma constante replicada, não uma chamada:
    // eles nunca se consultam em runtime (ADR-0001). Alterar o default de um
    // lado só faz o `previewBrcode` devolver `taxId: ""` em dev e o
    // `SendConversionFunding` recusar com `destinationUnverifiable` — sem que
    // nada aqui fique vermelho, porque todo teste sobrescreve os dois lados.
    $binance = envDefaultLiteral(
        base_path('app-modules/fake-binance/config/fake-binance-fiat.php'),
        'FAKE_BINANCE_FIAT_PIX_KEY',
    );

    $starkbank = envDefaultLiteral(
        base_path('app-modules/fake-starkbank/config/fake-starkbank-dict.php'),
        'FAKE_STARKBANK_FUNDING_PIX_KEY',
    );

    expect($starkbank)->toBe($binance)
        ->and($binance)->toBe('funding@fake-binance.dev');
});

it('mantém o taxId de funding no valor que o consumidor confere', function (): void {
    // Casa com `treasury.conversion.funding_expected_tax_id` do consumidor: é o
    // segundo guard do SendConversionFunding, e ele é fail-closed.
    $taxId = envDefaultLiteral(
        base_path('app-modules/fake-starkbank/config/fake-starkbank-dict.php'),
        'FAKE_STARKBANK_FUNDING_TAX_ID',
    );

    expect($taxId)->toBe('20.018.183/0001-80');
});

it('previsualiza, confere os guards, paga e liquida o BR Code que o fake-binance emitiu', function (): void {
    $brcode = new BuildStaticBrcode()->handle('250.00');

    $preview = (array) $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode($brcode),
        $this->signedHeaders(),
    )->assertOk()->json('previews.0');

    // Os dois guards do consumidor, com os dados que ele leria daqui.
    expect(preg_replace('/\D/', '', (string) $preview['taxId']))->toBe('20018183000180')
        ->and($preview['amount'])->toBe(25_000)
        ->and($preview['allowChange'])->toBeFalse();

    $id = (string) $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => $brcode,
        'taxId' => (string) $preview['taxId'],
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
    ]]])
        ->assertOk()
        ->assertJsonPath('payments.0.status', 'created')
        ->json('payments.0.id');

    $this->travel(121)->seconds();

    $this->getSigned('/v2/brcode-payment?status=success', $this->signedHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('payments.0.id', $id)
        ->assertJsonPath('payments.0.tags', ['conversion-abc-123']);

    // A releitura autoritativa concorda com o extrato, e o webhook saiu como
    // gatilho do mesmo desfecho.
    $this->getSigned('/v2/brcode-payment/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'success');

    $emission = WebhookEmission::query()->firstOrFail();

    expect($emission->event_type)->toBe(StarkbankEventType::Success)
        ->and($emission->entity_id)->toBe($id);
});

it('deixa o consumidor recusar sozinho quando o BR Code aponta para outra chave', function (): void {
    // O guard `destinationUnverifiable` de verdade: o código é legítimo, mas a
    // chave não é a que o DICT deste fake conhece.
    config(['fake-binance-fiat.pix_key' => 'outra-venue@exemplo.dev']);

    $preview = (array) $this->getSigned(
        '/v2/brcode-preview?brcodes='.rawurlencode(new BuildStaticBrcode()->handle('250.00')),
        $this->signedHeaders(),
    )->assertOk()->json('previews.0');

    expect($preview['taxId'])->toBeEmpty();
});

it('recusa o pagamento quando o taxId não é o titular da chave do código do fake-binance', function (): void {
    // O guard `destinationMismatch` do consumidor recusa antes; se ele falhar,
    // o provedor recusa depois — é esta a segunda barreira.
    $response = $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => new BuildStaticBrcode()->handle('250.00'),
        'taxId' => '11.222.333/0001-44',
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
    ]]]);

    $response->assertStatus(400)->assertJsonPath('errors.0.code', 'invalidTaxId');
});

it('recusa o pagamento de valor diferente do que a ordem do fake-binance cobrou', function (): void {
    $response = $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => new BuildStaticBrcode()->handle('250.00'),
        'taxId' => '20.018.183/0001-80',
        'amount' => 30_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
    ]]]);

    $response->assertStatus(400)->assertJsonPath('errors.0.code', 'invalidAmount');
});
