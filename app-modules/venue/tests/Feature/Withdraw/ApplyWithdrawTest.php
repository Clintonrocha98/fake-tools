<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\Venue\Ledger\Models\LedgerAccount;
use He4rt\Venue\Withdraw\Actions\ApplyWithdraw;
use He4rt\Venue\Withdraw\DTOs\ApplyWithdrawData;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Exceptions\UnsupportedWithdrawNetworkException;
use He4rt\Venue\Withdraw\Models\Withdrawal;

beforeEach(function (): void {
    config(['venue-withdraw.fees' => ['SOL' => '0.004']]);
});

it('debits amount+fee from the ledger and creates an awaiting-approval withdrawal', function (): void {
    (new CreditLedgerAccount)('USDC', '100');

    $withdrawal = (new ApplyWithdraw)(new ApplyWithdrawData(
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

    expect($account->free)->toBe('91.086000000000000000');
});

it('throws without creating a withdrawal when the ledger balance is insufficient', function (): void {
    (new CreditLedgerAccount)('USDC', '1');

    try {
        (new ApplyWithdraw)(new ApplyWithdrawData(
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

it('rejects a network absent from venue-withdraw.fees without creating a withdrawal or debiting', function (): void {
    (new CreditLedgerAccount)('USDC', '100');

    try {
        (new ApplyWithdraw)(new ApplyWithdrawData(
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
    (new CreditLedgerAccount)('USDC', '100');

    $data = new ApplyWithdrawData(
        coin: 'USDC',
        address: 'SomeSolanaAddress',
        amount: '8.91',
        network: 'SOL',
        withdrawOrderId: 'payout-3',
    );

    $first = (new ApplyWithdraw)($data);
    $second = (new ApplyWithdraw)($data);

    expect($second->id)->toBe($first->id)
        ->and(Withdrawal::query()->count())->toBe(1);

    $account = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($account->free)->toBe('91.086000000000000000');
});
