<?php

declare(strict_types=1);

use He4rt\FakeBinance\Deposit\Enums\DepositStatus;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;

it('announces a deposit that is born Pending, with the configured address and a synthetic txId', function (): void {
    $this->artisan('fake-binance:announce-deposit', ['coin' => 'usdc', 'amount' => '250.5', 'network' => 'sol'])
        ->assertSuccessful();

    $deposit = CryptoDeposit::query()->firstOrFail();

    expect($deposit->coin)->toBe('USDC')
        ->and($deposit->network)->toBe('SOL')
        ->and($deposit->amount)->toBe('250.500000000000000000')
        ->and($deposit->status)->toBe(DepositStatus::Pending)
        ->and($deposit->tx_id)->not->toBeEmpty()
        ->and($deposit->address)->toBe(config('fake-binance-deposit.addresses.SOL'))
        ->and($deposit->credited_at)->toBeNull()
        ->and(LedgerAccount::query()->exists())->toBeFalse();
});

it('honors an explicit --tx-id', function (): void {
    $this->artisan('fake-binance:announce-deposit', ['coin' => 'USDC', 'amount' => '10', 'network' => 'SOL', '--tx-id' => '0xexternal'])
        ->assertSuccessful();

    expect(CryptoDeposit::query()->firstOrFail()->tx_id)->toBe('0xexternal');
});

it('fails without creating anything when the network is unmapped', function (): void {
    $this->artisan('fake-binance:announce-deposit', ['coin' => 'USDC', 'amount' => '10', 'network' => 'BSC'])
        ->assertFailed();

    expect(CryptoDeposit::query()->count())->toBe(0);
});

it('fails on a non-numeric or non-positive amount', function (string $amount): void {
    $this->artisan('fake-binance:announce-deposit', ['coin' => 'USDC', 'amount' => $amount, 'network' => 'SOL'])
        ->assertFailed();

    expect(CryptoDeposit::query()->count())->toBe(0);
})->with(['abc', '0', '-5']);
