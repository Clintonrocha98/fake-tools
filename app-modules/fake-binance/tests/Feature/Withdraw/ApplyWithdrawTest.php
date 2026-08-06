<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Withdraw\Actions\ApplyWithdraw;
use He4rt\FakeBinance\Withdraw\DTOs\ApplyWithdrawData;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Exceptions\UnsupportedWithdrawNetworkException;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

beforeEach(function (): void {
    config(['fake-binance-withdraw.fees' => ['SOL' => '0.004']]);
});

it('debits exactly amount from the ledger and creates an awaiting-approval withdrawal', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $withdrawal = (new ApplyWithdraw)->handle(new ApplyWithdrawData(
        coin: 'USDC',
        address: 'SomeSolanaAddress',
        amount: '8.91',
        network: 'SOL',
        withdrawOrderId: 'payout-1',
    ));

    expect($withdrawal)->toBeInstanceOf(Withdrawal::class)
        ->and($withdrawal->status)->toBe(WithdrawStatus::AwaitingApproval)
        ->and($withdrawal->coin)->toBe('USDC')
        ->and($withdrawal->network)->toBe('SOL')
        ->and($withdrawal->transaction_fee)->toBe('0.004000000000000000')
        ->and($withdrawal->withdraw_order_id)->toBe('payout-1');

    $account = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($account->free)->toBe('91.090000000000000000');
});

it('accepts a withdraw of the exact available balance — the fee comes out of amount, never on top of it', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $withdrawal = (new ApplyWithdraw)->handle(new ApplyWithdrawData(
        coin: 'USDC',
        address: 'SomeSolanaAddress',
        amount: '100',
        network: 'SOL',
        withdrawOrderId: 'payout-full-balance',
    ));

    expect($withdrawal->amount)->toBe('100.000000000000000000')
        ->and($withdrawal->transaction_fee)->toBe('0.004000000000000000');

    $account = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($account->free)->toBe('0.000000000000000000');
});

it('throws without creating a withdrawal when the ledger balance is insufficient', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '1');

    try {
        (new ApplyWithdraw)->handle(new ApplyWithdrawData(
            coin: 'USDC',
            address: 'SomeSolanaAddress',
            amount: '8.91',
            network: 'SOL',
            withdrawOrderId: 'payout-2',
        ));
    } catch (InsufficientLedgerBalanceException) {
        // esperado
    }

    expect(Withdrawal::query()->count())->toBe(0);
});

it('rejects a network absent from fake-binance-withdraw.fees without creating a withdrawal or debiting', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    try {
        (new ApplyWithdraw)->handle(new ApplyWithdrawData(
            coin: 'USDC',
            address: 'SomeBscAddress',
            amount: '8.91',
            network: 'BSC',
            withdrawOrderId: 'payout-unmapped-network',
        ));
    } catch (UnsupportedWithdrawNetworkException) {
        // esperado
    }

    expect(Withdrawal::query()->count())->toBe(0);

    $account = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($account->free)->toBe('100.000000000000000000');
});

it('is idempotent: repeating the same withdrawOrderId does not debit twice', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $data = new ApplyWithdrawData(
        coin: 'USDC',
        address: 'SomeSolanaAddress',
        amount: '8.91',
        network: 'SOL',
        withdrawOrderId: 'payout-3',
    );

    $first = (new ApplyWithdraw)->handle($data);
    $second = (new ApplyWithdraw)->handle($data);

    expect($second->id)->toBe($first->id)
        ->and(Withdrawal::query()->count())->toBe(1);

    $account = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($account->free)->toBe('91.090000000000000000');
});
