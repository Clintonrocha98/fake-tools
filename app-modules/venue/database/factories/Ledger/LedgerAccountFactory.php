<?php

declare(strict_types=1);

namespace He4rt\Venue\Database\Factories\Ledger;

use He4rt\Venue\Ledger\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LedgerAccount> */
class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    public function definition(): array
    {
        return [
            'asset' => fake()->unique()->currencyCode(),
            'free' => '0',
            'locked' => '0',
        ];
    }
}
