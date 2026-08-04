<?php

declare(strict_types=1);

use He4rt\Venue\Withdraw\Actions\GetWithdrawHistory;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    config(['venue-withdraw.advance_seconds' => 60]);
});

it('returns the documented wire shape for a matching withdrawal', function (): void {
    $appliedAt = Date::parse('2019-10-12 11:12:02', 'UTC');

    $withdrawal = Withdrawal::factory()->create([
        'coin' => 'USDC',
        'network' => 'SOL',
        'address' => 'SomeAddress',
        'amount' => '8.91',
        'transaction_fee' => '0.004',
        'withdraw_order_id' => 'payout-1',
        'status' => WithdrawStatus::Completed,
        'tx_id' => '0xdeadbeef',
        'applied_at' => $appliedAt,
    ]);

    $rows = (new GetWithdrawHistory)(coin: 'USDC', withdrawOrderId: 'payout-1');

    expect($rows)->toHaveCount(1);

    $row = $rows[0]->jsonSerialize();

    expect($row)->toBe([
        'id' => $withdrawal->id,
        'withdrawOrderId' => 'payout-1',
        'coin' => 'USDC',
        'network' => 'SOL',
        'address' => 'SomeAddress',
        'amount' => '8.91',
        'transactionFee' => '0.004',
        'status' => 6,
        'txId' => '0xdeadbeef',
        'info' => null,
        'applyTime' => '2019-10-12 11:12:02',
    ]);
});

it('filters by coin and withdrawOrderId', function (): void {
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'payout-a']);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'payout-b']);
    Withdrawal::factory()->create(['coin' => 'USDT', 'withdraw_order_id' => 'payout-c']);

    $rows = (new GetWithdrawHistory)(coin: 'USDC', withdrawOrderId: 'payout-a');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->withdrawOrderId)->toBe('payout-a');
});

it('lazily advances AwaitingApproval to Processing once advance_seconds has elapsed', function (): void {
    Withdrawal::factory()->create([
        'withdraw_order_id' => 'payout-advance-1',
        'status' => WithdrawStatus::AwaitingApproval,
        'applied_at' => Date::now()->subSeconds(61),
    ]);

    $rows = (new GetWithdrawHistory)(coin: null, withdrawOrderId: 'payout-advance-1');

    expect($rows[0]->status)->toBe(WithdrawStatus::Processing->value);

    $persisted = Withdrawal::query()->where('withdraw_order_id', 'payout-advance-1')->firstOrFail();
    expect($persisted->status)->toBe(WithdrawStatus::Processing);
});

it('lazily advances all the way to Completed with a synthetic txId once two intervals elapsed', function (): void {
    Withdrawal::factory()->create([
        'withdraw_order_id' => 'payout-advance-2',
        'status' => WithdrawStatus::AwaitingApproval,
        'applied_at' => Date::now()->subSeconds(121),
    ]);

    $rows = (new GetWithdrawHistory)(coin: null, withdrawOrderId: 'payout-advance-2');

    expect($rows[0]->status)->toBe(WithdrawStatus::Completed->value)
        ->and($rows[0]->txId)->not->toBeNull();

    $persisted = Withdrawal::query()->where('withdraw_order_id', 'payout-advance-2')->firstOrFail();
    expect($persisted->status)->toBe(WithdrawStatus::Completed)
        ->and($persisted->tx_id)->not->toBeNull();
});

it('does not advance a withdrawal before advance_seconds has elapsed', function (): void {
    Withdrawal::factory()->create([
        'withdraw_order_id' => 'payout-advance-3',
        'status' => WithdrawStatus::AwaitingApproval,
        'applied_at' => Date::now()->subSeconds(10),
    ]);

    $rows = (new GetWithdrawHistory)(coin: null, withdrawOrderId: 'payout-advance-3');

    expect($rows[0]->status)->toBe(WithdrawStatus::AwaitingApproval->value);
});

it('never auto-advances a terminal manual-override status (Failure)', function (): void {
    Withdrawal::factory()->create([
        'withdraw_order_id' => 'payout-advance-4',
        'status' => WithdrawStatus::Failure,
        'info' => 'reason',
        'applied_at' => Date::now()->subSeconds(1_000),
    ]);

    $rows = (new GetWithdrawHistory)(coin: null, withdrawOrderId: 'payout-advance-4');

    expect($rows[0]->status)->toBe(WithdrawStatus::Failure->value)
        ->and($rows[0]->info)->toBe('reason');
});
