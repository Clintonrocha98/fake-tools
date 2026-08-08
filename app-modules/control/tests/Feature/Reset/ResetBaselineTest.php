<?php

declare(strict_types=1);

use He4rt\Control\Feed\Models\ControlEvent;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario as ArmVenueScenario;
use He4rt\FakeBinance\Scenarios\Actions\GetArmedScenario as GetArmedVenueScenario;
use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard as VenueSwitchboard;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario as ArmPixScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\GetArmedScenario as GetArmedPixScenario;
use He4rt\FakeStarkbank\Scenarios\Enums\InvoiceOutcome;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use He4rt\Identity\Users\User;

/*
|--------------------------------------------------------------------------
| POST /control/reset e control:reset-baseline
|--------------------------------------------------------------------------
|
| Devolve os dois fakes ao estado de partida para a próxima rodada do teste
| ponta a ponta começar limpa. Um `db:seed` não serve: o guard do
| `LedgerAccountSeeder` o faz não fazer nada com o ledger povoado, e é o
| "limpar antes de semear" que distingue este gesto.
|
*/

beforeEach(function (): void {
    config(['fake-binance-ledger.seed_balances' => 'BRL:100000,USDT:5000']);
});

it('devolve os saldos ao estado semeado e apaga as ordens', function (): void {
    LedgerAccount::query()->create(['asset' => 'BRL', 'free' => '42', 'locked' => '0']);
    FiatOrder::factory()->create();
    SpotOrder::factory()->create();
    Withdrawal::factory()->create();
    CryptoDeposit::factory()->create();

    $this->postJson('/control/reset')->assertOk();

    expect(FiatOrder::query()->count())->toBe(0)
        ->and(SpotOrder::query()->count())->toBe(0)
        ->and(Withdrawal::query()->count())->toBe(0)
        ->and(CryptoDeposit::query()->count())->toBe(0)
        ->and(LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('100000.000000000000000000')
        ->and(LedgerAccount::query()->where('asset', 'USDT')->sole()->free)->toBe('5000.000000000000000000');
});

it('apaga os recursos da malha PIX e ressemeia o DICT', function (): void {
    Invoice::factory()->create(['due' => now()->addDay()]);
    Transfer::factory()->create();
    BrcodePayment::factory()->create();
    WebhookEmission::factory()->create();
    DictEntry::factory()->create(['pix_key' => 'lixo-da-rodada@exemplo.dev']);

    $this->postJson('/control/reset')->assertOk();

    expect(Invoice::query()->count())->toBe(0)
        ->and(Transfer::query()->count())->toBe(0)
        ->and(BrcodePayment::query()->count())->toBe(0)
        ->and(WebhookEmission::query()->count())->toBe(0)
        ->and(DictEntry::query()->where('pix_key', 'lixo-da-rodada@exemplo.dev')->exists())->toBeFalse()
        // As duas chaves que o DICT nasce conhecendo voltam.
        ->and(DictEntry::query()->count())->toBe(2);
});

it('é idempotente: o segundo reset seguido é um no-op sem erro', function (): void {
    FiatOrder::factory()->create();

    $this->postJson('/control/reset')->assertOk();

    $depoisDoPrimeiro = LedgerAccount::query()->orderBy('asset')->pluck('free', 'asset')->all();

    $this->postJson('/control/reset')->assertOk()->assertJsonPath('total', 2 + 2);

    expect(LedgerAccount::query()->orderBy('asset')->pluck('free', 'asset')->all())->toBe($depoisDoPrimeiro);
});

it('preserva os eventos anteriores do feed e emite o marco depois deles', function (): void {
    $anterior = ControlEvent::factory()->create(['message' => 'evento da rodada anterior']);

    $this->postJson('/control/reset')->assertOk();

    $eventos = ControlEvent::query()->orderBy('id')->get();

    expect($eventos->first()->id)->toBe($anterior->id)
        ->and($eventos->pluck('message')->contains(fn (string $mensagem): bool => str_contains($mensagem, 'baseline resetada')))
        ->toBeTrue()
        ->and($eventos->where('channel', 'starkbank')->where('level', 'warning')->count())->toBe(1)
        ->and($eventos->where('channel', 'binance')->where('level', 'warning')->count())->toBe(1);
});

it('desarma os cenários e devolve o switchboard ao default nos dois fakes', function (): void {
    new ArmPixScenario()->handle(InvoiceOutcome::Expire);
    new ArmVenueScenario()->handle(SpotConversionOutcome::cases()[0]);

    $this->postJson('/control/binance/switchboard', [
        'switch' => ScenarioSwitch::Outage->value,
        'enabled' => true,
    ])->assertOk();

    $this->postJson('/control/reset')->assertOk();

    expect(new GetArmedPixScenario()->handle(PixLeg::StarkbankInvoice))->toBeNull()
        ->and(new GetArmedVenueScenario()->handle(VenueLeg::SpotConversion))->toBeNull()
        ->and(VenueSwitchboard::query()->count())->toBe(0);

    // A leitura seguinte recria o singleton desligado, como num container novo.
    $this->getJson('/control/binance/switchboard')
        ->assertOk()
        ->assertJsonPath('switchboard.switches.0.enabled', false);
});

it('não toca nas tabelas do identity — o dev não perde o login do painel', function (): void {
    $usuario = User::factory()->create();

    $this->postJson('/control/reset')->assertOk();

    expect(User::query()->whereKey($usuario->getKey())->exists())->toBeTrue();
});

it('recusa com 403 fora dos ambientes permitidos', function (): void {
    config(['control.reset.allowed_environments' => ['local']]);

    $resposta = $this->postJson('/control/reset')->assertStatus(403);

    expect($resposta->json('valid'))->toBe(['local']);
});

it('roda pelo comando artisan e reporta o que removeu', function (): void {
    FiatOrder::factory()->create();

    $this->artisan('control:reset-baseline')
        ->expectsOutputToContain('Baseline resetada')
        ->assertSuccessful();

    expect(FiatOrder::query()->count())->toBe(0);
});

it('faz o comando artisan falhar em vez de truncar fora do ambiente permitido', function (): void {
    config(['control.reset.allowed_environments' => ['production']]);

    FiatOrder::factory()->create();

    $this->artisan('control:reset-baseline')->assertFailed();

    expect(FiatOrder::query()->count())->toBe(1);
});
