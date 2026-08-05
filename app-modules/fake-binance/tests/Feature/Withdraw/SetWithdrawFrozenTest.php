<?php

declare(strict_types=1);

use He4rt\FakeBinance\Withdraw\Actions\AdvanceWithdrawStatus;
use He4rt\FakeBinance\Withdraw\Actions\SetWithdrawFrozen;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

it('toggles the frozen flag', function (): void {
    $withdrawal = Withdrawal::factory()->create();

    $frozen = (new SetWithdrawFrozen)->handle($withdrawal, frozen: true);
    expect($frozen->frozen)->toBeTrue();

    $unfrozen = (new SetWithdrawFrozen)->handle($frozen, frozen: false);
    expect($unfrozen->frozen)->toBeFalse();
});

it('a frozen withdrawal never advances, even past both advance windows', function (): void {
    config(['fake-binance-withdraw.advance_seconds' => 60]);

    $withdrawal = Withdrawal::factory()->create([
        'status' => WithdrawStatus::AwaitingApproval,
        'applied_at' => Date::now()->subSeconds(200),
    ]);
    (new SetWithdrawFrozen)->handle($withdrawal, frozen: true);

    $advanced = (new AdvanceWithdrawStatus)->handle($withdrawal->refresh());

    expect($advanced->status)->toBe(WithdrawStatus::AwaitingApproval);
});
