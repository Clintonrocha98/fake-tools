<?php

declare(strict_types=1);

use He4rt\Venue\Withdraw\Actions\ForceWithdrawStatus;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;

it('forces one of the three documented failure statuses with info filled', function (WithdrawStatus $status): void {
    $withdrawal = Withdrawal::factory()->create(['status' => WithdrawStatus::AwaitingApproval]);

    $forced = (new ForceWithdrawStatus)->handle($withdrawal, $status, 'reason from the panel');

    expect($forced->status)->toBe($status)
        ->and($forced->info)->toBe('reason from the panel');
})->with([
    'cancelled' => [WithdrawStatus::Cancelled],
    'rejected' => [WithdrawStatus::Rejected],
    'failure' => [WithdrawStatus::Failure],
]);

it('clears any raw_status_override: the two overrides are mutually exclusive', function (): void {
    $withdrawal = Withdrawal::factory()->create([
        'status' => WithdrawStatus::AwaitingApproval,
        'raw_status_override' => 99,
    ]);

    $forced = (new ForceWithdrawStatus)->handle($withdrawal, WithdrawStatus::Rejected, 'bad address');

    expect($forced->raw_status_override)->toBeNull();
});
