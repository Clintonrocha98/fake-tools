<?php

declare(strict_types=1);

use He4rt\Venue\Withdraw\Actions\CompleteWithdrawNow;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;

it('completes a withdrawal immediately with a synthetic tx id', function (): void {
    $withdrawal = Withdrawal::factory()->create(['status' => WithdrawStatus::AwaitingApproval]);

    $completed = (new CompleteWithdrawNow)($withdrawal);

    expect($completed->status)->toBe(WithdrawStatus::Completed)
        ->and($completed->tx_id)->not->toBeNull()
        ->and($completed->tx_id)->toStartWith('0x');
});

it('clears any raw_status_override', function (): void {
    $withdrawal = Withdrawal::factory()->create([
        'status' => WithdrawStatus::AwaitingApproval,
        'raw_status_override' => 99,
    ]);

    $completed = (new CompleteWithdrawNow)($withdrawal);

    expect($completed->raw_status_override)->toBeNull();
});
