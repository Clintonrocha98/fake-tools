<?php

declare(strict_types=1);

use He4rt\FakeBinance\Withdraw\Actions\CompleteWithdrawNow;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

it('completes a withdrawal immediately with a synthetic tx id in the network format', function (): void {
    $withdrawal = Withdrawal::factory()->create(['network' => 'ETH', 'status' => WithdrawStatus::AwaitingApproval]);

    $completed = (new CompleteWithdrawNow)->handle($withdrawal);

    expect($completed->status)->toBe(WithdrawStatus::Completed)
        ->and($completed->tx_id)->toMatch('/^0x[0-9a-f]{64}$/');
});

it('completes a Solana withdrawal with a base58 tx id, never an 0x hash', function (): void {
    $withdrawal = Withdrawal::factory()->create(['network' => 'SOL', 'status' => WithdrawStatus::AwaitingApproval]);

    $completed = (new CompleteWithdrawNow)->handle($withdrawal);

    expect($completed->tx_id)->toMatch('/^[1-9A-HJ-NP-Za-km-z]{88}$/')
        ->and($completed->tx_id)->not->toStartWith('0x');
});

it('clears any raw_status_override', function (): void {
    $withdrawal = Withdrawal::factory()->create([
        'status' => WithdrawStatus::AwaitingApproval,
        'raw_status_override' => 99,
    ]);

    $completed = (new CompleteWithdrawNow)->handle($withdrawal);

    expect($completed->raw_status_override)->toBeNull();
});
