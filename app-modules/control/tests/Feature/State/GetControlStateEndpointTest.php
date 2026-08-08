<?php

declare(strict_types=1);

use He4rt\Control\Feed\Models\ControlEvent;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario as ArmBinanceScenario;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard as BinanceSwitchboard;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario as ArmPixScenario;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

/*
|--------------------------------------------------------------------------
| GET /control/state
|--------------------------------------------------------------------------
|
| O retrato diz onde os fakes ESTÃO; o feed conta o que aconteceu. A armadilha
| central: as Actions de leitura dos dois fakes fazem avanço lazy — ler é o que
| faz o tempo passar, e `GetFiatOrderDetail` chega a creditar o ledger. Um
| snapshot que passasse por elas viraria uma máquina de avançar o tempo, porque
| a sidebar consulta em loop.
|
*/

it('serve as duas metades com switchboard e cenários armados', function (): void {
    new ArmPixScenario()->handle(InvoiceOutcome::cases()[0]);
    new ArmBinanceScenario()->handle(SpotConversionOutcome::cases()[0]);

    $resposta = $this->getJson('/control/state')->assertOk();

    expect($resposta->json('starkbank.armedScenarios'))->toHaveCount(1)
        ->and($resposta->json('binance.armedScenarios'))->toHaveCount(1)
        ->and($resposta->json('starkbank.switchboard.switches'))->not->toBeEmpty()
        ->and($resposta->json('binance.switchboard.switches'))->not->toBeEmpty();
});

it('traz o cenário armado com a perna e o outcome', function (): void {
    $outcome = InvoiceOutcome::cases()[0];

    new ArmPixScenario()->handle($outcome);

    $this->getJson('/control/state')
        ->assertOk()
        ->assertJsonPath('starkbank.armedScenarios.0.leg', $outcome->leg()->value)
        ->assertJsonPath('starkbank.armedScenarios.0.outcome', (string) $outcome->value)
        ->assertJsonPath('starkbank.armedScenarios.0.outcomeLabel', $outcome->getLabel());
});

it('não altera o status de nenhuma perna em dez consultas seguidas', function (): void {
    // Todas nascem maduras o bastante para o avanço lazy pegá-las na primeira
    // leitura — se o snapshot passasse por uma Action de leitura, o assert
    // final quebraria.
    config([
        'fake-starkbank-invoice.advance_seconds' => 1,
        'fake-starkbank-transfer.advance_seconds' => 1,
        'fake-starkbank-brcode.advance_seconds' => 1,
        'fake-binance-fiat.advance_seconds' => 1,
        'fake-binance-withdraw.advance_seconds' => 1,
    ]);

    $invoice = Invoice::factory()->create(['created_at' => now()->subHour(), 'due' => now()->addDay()]);
    $transfer = Transfer::factory()->create(['created_at' => now()->subHour()]);
    $brcode = BrcodePayment::factory()->create(['created_at' => now()->subHour()]);
    $fiat = FiatOrder::factory()->create(['created_at' => now()->subHour(), 'status' => FiatOrderStatus::Processing]);
    $saque = Withdrawal::factory()->create(['applied_at' => now()->subHour()]);

    $statusAntes = [
        'invoice' => $invoice->status,
        'transfer' => $transfer->status,
        'brcode' => $brcode->status,
        'saque' => $saque->status,
    ];

    for ($i = 0; $i < 10; $i++) {
        $this->getJson('/control/state')->assertOk();
    }

    expect($invoice->refresh()->status)->toBe($statusAntes['invoice'])
        ->and($transfer->refresh()->status)->toBe($statusAntes['transfer'])
        ->and($brcode->refresh()->status)->toBe($statusAntes['brcode'])
        ->and($saque->refresh()->status)->toBe($statusAntes['saque'])
        ->and($fiat->refresh()->status)->toBe(FiatOrderStatus::Processing);
});

it('não move saldo do ledger nem preenche credited_at de uma fiat order', function (): void {
    config(['fake-binance-fiat.advance_seconds' => 1]);

    LedgerAccount::query()->create(['asset' => 'BRL', 'free' => '1000', 'locked' => '0']);

    $ordem = FiatOrder::factory()->create([
        'created_at' => now()->subHour(),
        'status' => FiatOrderStatus::Processing,
        'currency' => 'BRL',
        'amount' => '500',
        'credited_at' => null,
    ]);

    for ($i = 0; $i < 10; $i++) {
        $this->getJson('/control/state')->assertOk();
    }

    expect(FiatOrder::query()->find($ordem->id)->credited_at)->toBeNull()
        ->and(LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('1000.000000000000000000');
});

it('não conta uma leitura de brcode na fiat order', function (): void {
    $ordem = FiatOrder::factory()->create(['brcode_reads_count' => 0]);

    $this->getJson('/control/state')->assertOk();

    expect(FiatOrder::query()->find($ordem->id)->brcode_reads_count)->toBe(0);
});

it('não cria a linha singleton do switchboard só de ser observado', function (): void {
    expect(BinanceSwitchboard::query()->count())->toBe(0);

    $this->getJson('/control/state')->assertOk();

    expect(BinanceSwitchboard::query()->count())->toBe(0);
});

it('projeta o avanço pendente sem disparar o avanço', function (): void {
    config(['fake-starkbank-invoice.advance_seconds' => 3_600]);

    Invoice::factory()->create(['created_at' => now()->subMinutes(30), 'due' => now()->addDay()]);

    $resposta = $this->getJson('/control/state')->assertOk();

    expect($resposta->json('starkbank.invoices.rows.0.advance.pending'))->toBeFalse()
        ->and($resposta->json('starkbank.invoices.rows.0.advance.secondsUntil'))->toBeGreaterThan(0);
});

it('rotula o bloqueio quando o registro está congelado', function (): void {
    Invoice::factory()->create(['frozen' => true, 'due' => now()->addDay()]);

    $this->getJson('/control/state')
        ->assertOk()
        ->assertJsonPath('starkbank.invoices.rows.0.advance.blockedBy', 'frozen')
        ->assertJsonPath('starkbank.invoices.rows.0.advance.pending', false);
});

it('respeita o limite por tipo e ainda reporta o total', function (): void {
    Invoice::factory()->count(5)->create(['due' => now()->addDay()]);

    $resposta = $this->getJson('/control/state?limit=2')->assertOk();

    expect($resposta->json('starkbank.invoices.rows'))->toHaveCount(2)
        ->and($resposta->json('starkbank.invoices.total'))->toBe(5);
});

it('cobre todos os tipos de recurso dos dois fakes', function (): void {
    $resposta = $this->getJson('/control/state')->assertOk();

    expect(array_keys((array) $resposta->json('starkbank')))->toEqualCanonicalizing([
        'switchboard', 'armedScenarios', 'invoices', 'transfers', 'brcodePayments', 'webhookEmissions', 'dictEntries',
    ])->and(array_keys((array) $resposta->json('binance')))->toEqualCanonicalizing([
        'switchboard', 'armedScenarios', 'ledger', 'fiatOrders', 'spotOrders', 'withdrawals', 'cryptoDeposits',
    ]);
});

it('serve os recursos existentes de cada tipo', function (): void {
    Invoice::factory()->create(['due' => now()->addDay()]);
    Transfer::factory()->create();
    BrcodePayment::factory()->create();
    WebhookEmission::factory()->create();
    DictEntry::factory()->create();
    LedgerAccount::query()->create(['asset' => 'USDT', 'free' => '10', 'locked' => '0']);
    FiatOrder::factory()->create();
    SpotOrder::factory()->create();
    Withdrawal::factory()->create();
    CryptoDeposit::factory()->create();

    $resposta = $this->getJson('/control/state')->assertOk();

    foreach (['invoices', 'transfers', 'brcodePayments', 'webhookEmissions', 'dictEntries'] as $tipo) {
        expect($resposta->json('starkbank.'.$tipo.'.total'))->toBe(1, $tipo);
    }

    foreach (['ledger', 'fiatOrders', 'spotOrders', 'withdrawals', 'cryptoDeposits'] as $tipo) {
        expect($resposta->json('binance.'.$tipo.'.total'))->toBe(1, $tipo);
    }
});

it('não gera evento no próprio feed ao ser consultado', function (): void {
    $antes = ControlEvent::query()->count();

    $this->getJson('/control/state')->assertOk();

    expect(ControlEvent::query()->count())->toBe($antes);
});
