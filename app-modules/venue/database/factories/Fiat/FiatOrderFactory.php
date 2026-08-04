<?php

declare(strict_types=1);

namespace He4rt\Venue\Database\Factories\Fiat;

use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use He4rt\Venue\Fiat\Models\FiatOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiatOrder> */
class FiatOrderFactory extends Factory
{
    protected $model = FiatOrder::class;

    public function definition(): array
    {
        return [
            'order_no' => fake()->unique()->numerify(str_repeat('#', 16)),
            'currency' => 'BRL',
            'payment_method' => 'Pix',
            'amount' => fake()->randomFloat(2, 100, 10_000),
            'status' => FiatOrderStatus::Processing,
            'forced_status' => null,
            'brcode' => sprintf('000201BR.GOV.BCB.PIX-FAKE-%s-EMV', fake()->unique()->numerify(str_repeat('#', 8))),
            'credited_at' => null,
        ];
    }

    public function processing(): self
    {
        return $this->state(['status' => FiatOrderStatus::Processing, 'credited_at' => null]);
    }

    public function credited(): self
    {
        return $this->state([
            'status' => FiatOrderStatus::Success,
            'credited_at' => now(),
        ]);
    }

    public function forced(FiatOrderStatus $status): self
    {
        return $this->state(['forced_status' => $status]);
    }
}
