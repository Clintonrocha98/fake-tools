<?php

declare(strict_types=1);

use He4rt\Venue\Withdraw\Actions\AdvanceWithdrawStatus;
use He4rt\Venue\Withdraw\Actions\SetWithdrawFrozen;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

it('toggles the frozen flag', function (): void {
    $withdrawal = Withdrawal::factory()->create();

    $frozen = (new SetWithdrawFrozen)($withdrawal, true);
    expect($frozen->frozen)->toBeTrue();

    $unfrozen = (new SetWithdrawFrozen)($frozen, false);
    expect($unfrozen->frozen)->toBeFalse();
});

it('a frozen withdrawal never advances, even past both advance windows', function (): void {
    config(['venue-withdraw.advance_seconds' => 60]);

    $withdrawal = Withdrawal::factory()->create([
        'status' => WithdrawStatus::AwaitingApproval,
        'applied_at' => Date::now()->subSeconds(200),
    ]);
    (new SetWithdrawFrozen)($withdrawal, true);

    $advanced = (new AdvanceWithdrawStatus)($withdrawal->refresh());

    expect($advanced->status)->toBe(WithdrawStatus::AwaitingApproval);
});
