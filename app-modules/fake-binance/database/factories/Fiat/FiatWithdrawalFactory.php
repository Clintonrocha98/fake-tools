<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Database\Factories\Fiat;

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatWithdrawal;
use He4rt\FakeBinance\Fiat\Support\FiatOrderNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<FiatWithdrawal>
 */
final class FiatWithdrawalFactory extends Factory
{
    protected $model = FiatWithdrawal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => FiatOrderNumber::generate(
                static fn (string $candidate): bool => FiatWithdrawal::query()->where('order_id', $candidate)->exists(),
            ),
            'currency' => 'BRL',
            'payment_method' => 'bank_transfer',
            'amount' => fake()->randomFloat(2, 10, 10_000),
            'account_number' => (string) fake()->randomNumber(8),
            'agency' => '0001',
            'bank_code_for_pix' => '260',
            'account_type' => 'current',
            'client_order_id' => (string) Str::uuid(),
            'status' => FiatOrderStatus::Processing,
            'requested_at' => Date::now(),
        ];
    }
}
