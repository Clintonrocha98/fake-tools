<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Brcode\Enums\BrcodePaymentStatus;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\BrcodePaymentOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Tests\Support\BuildsBrcodes;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class, BuildsBrcodes::class);

/*
|--------------------------------------------------------------------------
| Perna assíncrona: brcode-payment
|--------------------------------------------------------------------------
|
| O armado é consumido no POST, DEPOIS dos guards do funding — um pagamento
| recusado por taxId ou valor divergente nunca existiu e não gasta o cenário.
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
function armedPaymentPayload(string $brcode, array $overrides = []): array
{
    return ['payments' => [array_merge([
        'brcode' => $brcode,
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'tags' => ['conversion-abc-123'],
        'description' => 'BRD funding conversion-abc-123',
    ], $overrides)]];
}

it('faz o próximo pagamento nascer destinado a failed com o motivo armado', function (): void {
    new ArmScenario()->handle(BrcodePaymentOutcome::Fail, new PixScenarioPayload(reason: 'Saldo insuficiente na conta de origem'));

    /** @var string $id */
    $id = $this->postSigned('/v2/brcode-payment', armedPaymentPayload($this->staticBrcode(amount: '250.00')))
        ->assertOk()
        ->json('payments.0.id');

    $payment = BrcodePayment::query()->findOrFail($id);

    expect($payment->destined_status)->toBe(BrcodePaymentStatus::Failed)
        ->and($payment->failure_reason)->toBe('Saldo insuficiente na conta de origem');

    $this->getSigned('/v2/brcode-payment/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'failed');
});

it('usa o motivo default quando o operador arma Fail sem digitar um', function (): void {
    new ArmScenario()->handle(BrcodePaymentOutcome::Fail);

    /** @var string $id */
    $id = $this->postSigned('/v2/brcode-payment', armedPaymentPayload($this->staticBrcode(amount: '250.00')))
        ->assertOk()
        ->json('payments.0.id');

    expect(BrcodePayment::query()->findOrFail($id)->failure_reason)->toBe('Pagamento recusado pela rede');
});

it('segura o pagamento retido em processing, por mais que o relógio ande', function (): void {
    new ArmScenario()->handle(BrcodePaymentOutcome::Hold);

    /** @var string $id */
    $id = $this->postSigned('/v2/brcode-payment', armedPaymentPayload($this->staticBrcode(amount: '250.00')))
        ->assertOk()
        ->json('payments.0.id');

    expect(BrcodePayment::query()->findOrFail($id)->held)->toBeTrue();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(2));
    $this->getSigned('/v2/brcode-payment/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'processing');

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHour());
    $this->getSigned('/v2/brcode-payment/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'processing');

    CarbonImmutable::setTestNow();
});

it('consome o armado no POST e devolve o pagamento seguinte ao neutro', function (): void {
    new ArmScenario()->handle(BrcodePaymentOutcome::Hold);

    $brcode = $this->staticBrcode(amount: '250.00');

    /** @var string $armado */
    $armado = $this->postSigned('/v2/brcode-payment', armedPaymentPayload($brcode))->assertOk()->json('payments.0.id');
    /** @var string $neutro */
    $neutro = $this->postSigned('/v2/brcode-payment', armedPaymentPayload($brcode))->assertOk()->json('payments.0.id');

    expect(ArmedScenario::query()->count())->toBe(0)
        ->and(BrcodePayment::query()->findOrFail($armado)->held)->toBeTrue()
        ->and(BrcodePayment::query()->findOrFail($neutro)->held)->toBeFalse();
});

it('não gasta o armado num pagamento recusado pelos guards do funding', function (): void {
    new ArmScenario()->handle(BrcodePaymentOutcome::Fail);

    $this->postSigned('/v2/brcode-payment', armedPaymentPayload($this->staticBrcode(amount: '250.00'), [
        'amount' => 99_900,
    ]))->assertStatus(400)->assertJsonPath('errors.0.code', 'invalidAmount');

    expect(ArmedScenario::query()->count())->toBe(1)
        ->and(BrcodePayment::query()->count())->toBe(0);
});
