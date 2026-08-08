<?php

declare(strict_types=1);

use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

/*
|--------------------------------------------------------------------------
| Comandos da venue
|--------------------------------------------------------------------------
*/

it('credita a fiat order uma única vez, mesmo chamando duas', function (): void {
    LedgerAccount::query()->create(['asset' => 'BRL', 'free' => '0', 'locked' => '0']);

    $ordem = FiatOrder::factory()->create([
        'status' => FiatOrderStatus::Success,
        'currency' => 'BRL',
        'amount' => '250',
        'credited_at' => null,
    ]);

    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/credit')->assertOk();
    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/credit')->assertOk();

    expect(LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('250.000000000000000000')
        ->and($ordem->refresh()->credited_at)->not->toBeNull();
});

it('força o status da fiat order pela máscara de leitura', function (): void {
    $ordem = FiatOrder::factory()->create(['status' => FiatOrderStatus::Processing]);

    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/force', ['status' => 'failed'])
        ->assertOk()
        ->assertJsonPath('fiatOrder.forcedStatus', 'failed');

    expect($ordem->refresh()->forced_status)->toBe(FiatOrderStatus::Failed);
});

it('recusa com 422 um status que a fiat order não conhece', function (): void {
    $ordem = FiatOrder::factory()->create();

    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/force', ['status' => 'Estornada'])
        ->assertStatus(422);
});

it('responde 404 numa fiat order que não existe', function (): void {
    $this->postJson('/control/binance/fiat-orders/nao-existe/credit')->assertNotFound();
});

it('congela a fiat order e atrasa o brcode', function (): void {
    $ordem = FiatOrder::factory()->create(['frozen' => false, 'brcode_delay_reads' => null]);

    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/freeze', ['frozen' => true])->assertOk();
    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/delay-brcode', ['reads' => 3])->assertOk();

    expect($ordem->refresh()->frozen)->toBeTrue()
        ->and($ordem->brcode_delay_reads)->toBe(3);

    // `reads` omitido restaura o default: brcode imediato.
    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/delay-brcode')->assertOk();

    expect($ordem->refresh()->brcode_delay_reads)->toBeNull();
});

it('emite um status de wire arbitrário na fiat order', function (): void {
    $ordem = FiatOrder::factory()->create();

    $this->postJson('/control/binance/fiat-orders/'.$ordem->order_no.'/emit-unknown-status', ['wireStatus' => 'Quarantined', 'rawStatus' => 'Quarantined'])
        ->assertOk()
        ->assertJsonPath('fiatOrder.forcedWireStatus', 'Quarantined');
});

it('rejeita e expira parcialmente uma spot order', function (): void {
    // Os dois gestos revertem o fill no ledger ({@see ReverseSpotOrderLedgerFill}),
    // então o ativo recebido precisa estar lá para ser debitado de volta.
    LedgerAccount::query()->create(['asset' => 'USDC', 'free' => '1000', 'locked' => '0']);
    LedgerAccount::query()->create(['asset' => 'BRL', 'free' => '1000', 'locked' => '0']);

    $rejeitada = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);

    $this->postJson('/control/binance/spot-orders/'.$rejeitada->order_id.'/reject')
        ->assertOk()
        ->assertJsonPath('spotOrder.status', OrderStatus::Rejected->value);

    $parcial = SpotOrder::factory()->create(['status' => OrderStatus::Filled]);

    $this->postJson('/control/binance/spot-orders/'.$parcial->order_id.'/expire-partially')->assertOk();

    expect($parcial->refresh()->status)->toBe(OrderStatus::Expired);
});

it('emite um status arbitrário na spot order', function (): void {
    $ordem = SpotOrder::factory()->create();

    $this->postJson('/control/binance/spot-orders/'.$ordem->order_id.'/emit-unknown-status', ['rawStatus' => 'PENDING_CANCEL'])
        ->assertOk()
        ->assertJsonPath('spotOrder.rawStatusOverride', 'PENDING_CANCEL');
});

it('responde 404 numa spot order que não existe', function (): void {
    $this->postJson('/control/binance/spot-orders/999999999/reject')->assertNotFound();
});

it('completa, força e congela o saque', function (): void {
    $saque = Withdrawal::factory()->create(['status' => WithdrawStatus::Processing]);

    $this->postJson('/control/binance/withdrawals/'.$saque->id.'/complete')
        ->assertOk()
        ->assertJsonPath('withdrawal.status', (string) WithdrawStatus::Completed->value);

    $this->postJson('/control/binance/withdrawals/'.$saque->id.'/force', [
        'status' => (string) WithdrawStatus::Failure->value,
        'info' => 'rede indisponível',
    ])->assertOk();

    expect($saque->refresh()->status)->toBe(WithdrawStatus::Failure)
        ->and($saque->info)->toBe('rede indisponível');

    $this->postJson('/control/binance/withdrawals/'.$saque->id.'/freeze', ['frozen' => true])->assertOk();

    expect($saque->refresh()->frozen)->toBeTrue();
});

it('avança o saque maduro sob comando', function (): void {
    config(['fake-binance-withdraw.advance_seconds' => 1]);

    $saque = Withdrawal::factory()->create([
        'status' => WithdrawStatus::Processing,
        'applied_at' => now()->subHour(),
    ]);

    $this->postJson('/control/binance/withdrawals/'.$saque->id.'/advance')->assertOk();

    expect($saque->refresh()->status)->toBe(WithdrawStatus::Completed);
});

it('emite um status numérico arbitrário no saque', function (): void {
    $saque = Withdrawal::factory()->create();

    $this->postJson('/control/binance/withdrawals/'.$saque->id.'/emit-unknown-status', ['rawStatus' => 99])
        ->assertOk()
        ->assertJsonPath('withdrawal.rawStatusOverride', 99);
});

it('atribui, credita e debita saldo do ledger', function (): void {
    $this->postJson('/control/binance/ledger/USDT/set', ['free' => '100', 'locked' => '5'])
        ->assertOk()
        ->assertJsonPath('ledgerAccount.asset', 'USDT');

    expect(LedgerAccount::query()->where('asset', 'USDT')->sole()->free)->toBe('100.000000000000000000');

    $this->postJson('/control/binance/ledger/USDT/credit', ['amount' => '50'])->assertOk();

    expect(LedgerAccount::query()->where('asset', 'USDT')->sole()->free)->toBe('150.000000000000000000');

    $this->postJson('/control/binance/ledger/USDT/debit', ['amount' => '30'])->assertOk();

    expect(LedgerAccount::query()->where('asset', 'USDT')->sole()->free)->toBe('120.000000000000000000');
});

it('traduz o saldo insuficiente para 422, nunca 500', function (): void {
    $this->postJson('/control/binance/ledger/USDT/set', ['free' => '10'])->assertOk();

    $this->postJson('/control/binance/ledger/USDT/debit', ['amount' => '1000'])->assertStatus(422);
});

it('anuncia uma chegada de cripto sem mover saldo', function (): void {
    LedgerAccount::query()->create(['asset' => 'USDT', 'free' => '0', 'locked' => '0']);

    $this->postJson('/control/binance/crypto-deposits', [
        'coin' => 'USDT',
        'network' => 'TRX',
        'amount' => '500',
    ])
        ->assertCreated()
        ->assertJsonPath('cryptoDeposit.coin', 'USDT');

    expect(CryptoDeposit::query()->count())->toBe(1)
        ->and(LedgerAccount::query()->where('asset', 'USDT')->sole()->free)->toBe('0.000000000000000000');
});

it('avança a chegada de cripto sob comando', function (): void {
    config(['fake-binance-deposit.advance_seconds' => 1]);

    $deposito = CryptoDeposit::factory()->create(['announced_at' => now()->subHour(), 'credited_at' => null]);

    $this->postJson('/control/binance/crypto-deposits/'.$deposito->id.'/advance')->assertOk();

    expect($deposito->refresh()->credited_at)->not->toBeNull();
});

it('recusa o anúncio de cripto sem os campos obrigatórios', function (): void {
    $this->postJson('/control/binance/crypto-deposits', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['coin', 'network', 'amount']);
});
