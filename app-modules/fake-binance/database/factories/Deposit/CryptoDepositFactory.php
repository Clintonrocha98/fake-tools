<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Database\Factories\Deposit;

use He4rt\FakeBinance\Deposit\Enums\DepositStatus;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Withdraw\Support\SyntheticTxId;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<CryptoDeposit>
 */
final class CryptoDepositFactory extends Factory
{
    protected $model = CryptoDeposit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coin' => 'USDC',
            'network' => 'SOL',
            'address' => 'FakeBinanceSolDepositAddress1111111111111111',
            'address_tag' => null,
            'amount' => fake()->randomFloat(2, 1, 1_000),
            'tx_id' => SyntheticTxId::forNetwork('SOL'),
            'status' => DepositStatus::Pending,
            'announced_at' => Date::now(),
            'credited_at' => null,
        ];
    }

    public function credited(): self
    {
        return $this->state(fn (): array => [
            'status' => DepositStatus::Credited,
            'credited_at' => Date::now(),
        ]);
    }
}
