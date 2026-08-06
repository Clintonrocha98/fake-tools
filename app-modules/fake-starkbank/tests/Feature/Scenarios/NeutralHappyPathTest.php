<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Database\Seeders\DictEntrySeeder;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard;
use He4rt\FakeStarkbank\Tests\Support\BuildsBrcodes;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class, BuildsBrcodes::class);

/*
|--------------------------------------------------------------------------
| Sem nenhum cenário armado, o happy path é o de sempre
|--------------------------------------------------------------------------
|
| É o invariante que justifica o mecanismo inteiro: o switchboard existe, as
| quatro pernas o consultam, e mesmo assim NADA muda enquanto ninguém clica. Um
| plano neutro que já desviasse alguma coisa transformaria o cenário no novo
| comportamento padrão do fake.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config([
        'fake-starkbank-invoice.advance_seconds' => 60,
        'fake-starkbank-transfer.advance_seconds' => 60,
        'fake-starkbank-brcode.advance_seconds' => 60,
    ]);

    $this->seed(DictEntrySeeder::class);
});

it('emite, avança e liquida a invoice exatamente como antes do mecanismo existir', function (): void {
    /** @var string $id */
    $id = $this->postSigned('/v2/invoice', ['invoices' => [[
        'amount' => 10_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'due' => CarbonImmutable::now()->addDay()->toIso8601String(),
        'expiration' => 3_600,
        'tags' => ['deposit-abc-123'],
    ]]])->assertOk()->json('invoices.0.id');

    $invoice = Invoice::query()->findOrFail($id);

    expect($invoice->destined_status)->toBeNull()
        ->and($invoice->extra_advance_seconds)->toBe(0)
        ->and($invoice->frozen)->toBeFalse();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(2));

    $this->getSigned('/v2/invoice/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('invoice.status', 'paid');

    CarbonImmutable::setTestNow();
});

it('despacha e liquida a transfer exatamente como antes do mecanismo existir', function (): void {
    /** @var string $id */
    $id = $this->postSigned('/v2/transfer', ['transfers' => [[
        'amount' => 5_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'bankCode' => '20018183',
        'branchCode' => '*2Nq4Yw7cBw9utBZAgLOg4kKm8xNXJkYkqUzletoeOPM=',
        'accountNumber' => '*PnAoBLxISgcZ4Widbyqv0rvQx/NWaFn5NQXg/LpyFyIC',
        'accountType' => 'checking',
        'externalId' => 'payout-neutro-1',
        'tags' => ['payout-neutro-1'],
    ]]])->assertOk()->json('transfers.0.id');

    $transfer = Transfer::query()->findOrFail($id);

    expect($transfer->destined_status)->toBeNull()
        ->and($transfer->held)->toBeFalse()
        ->and($transfer->failure_reason)->toBeNull();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(3));

    $this->getSigned('/v2/transfer/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'success');

    CarbonImmutable::setTestNow();
});

it('paga e liquida o funding exatamente como antes do mecanismo existir', function (): void {
    /** @var string $id */
    $id = $this->postSigned('/v2/brcode-payment', ['payments' => [[
        'brcode' => $this->staticBrcode(amount: '250.00'),
        'taxId' => '20.018.183/0001-80',
        'amount' => 25_000,
        'tags' => ['conversion-neutro-1'],
        'description' => 'BRD funding conversion-neutro-1',
    ]]])->assertOk()->json('payments.0.id');

    $payment = BrcodePayment::query()->findOrFail($id);

    expect($payment->destined_status)->toBeNull()
        ->and($payment->held)->toBeFalse();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(3));

    $this->getSigned('/v2/brcode-payment/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('payment.status', 'success');

    CarbonImmutable::setTestNow();
});

it('não grava nem cenário armado nem switchboard ligado ao atravessar o happy path', function (): void {
    $this->getSigned('/v2/workspace', $this->signedHeaders())->assertOk();

    expect(ArmedScenario::query()->count())->toBe(0)
        ->and(ScenarioSwitchboard::query()->sole()->outage_mode)->toBeFalse()
        ->and(ScenarioSwitchboard::query()->sole()->rate_limit_mode)->toBeFalse();
});
