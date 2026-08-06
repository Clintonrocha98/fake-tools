<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Database\Factories\Fiat;

use He4rt\FakeBinance\Fiat\Actions\BuildStaticBrcode;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
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
            // O brcode nasce do `amount` já resolvido (inclusive quando um state
            // o sobrescreve), como no depósito real — o campo 54 do EMV e a
            // coluna nunca divergem.
            'brcode' => static function (array $attributes): string {
                $amount = $attributes['amount'] ?? '0';

                return (new BuildStaticBrcode)->handle(is_numeric($amount) ? (string) $amount : '0');
            },
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
