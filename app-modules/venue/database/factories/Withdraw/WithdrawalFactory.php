<?php

declare(strict_types=1);

namespace He4rt\Venue\Database\Factories\Withdraw;

use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Withdrawal> */
class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    public function definition(): array
    {
        return [
            'coin' => 'USDC',
            'network' => 'SOL',
            'address' => fake()->regexify('[A-Za-z0-9]{32,44}'),
            'address_tag' => null,
            'amount' => fake()->randomFloat(8, 1, 1_000),
            'transaction_fee' => '0.004',
            'withdraw_order_id' => fake()->unique()->uuid(),
            'status' => WithdrawStatus::AwaitingApproval,
            'tx_id' => null,
            'info' => null,
            'applied_at' => now(),
        ];
    }
}
