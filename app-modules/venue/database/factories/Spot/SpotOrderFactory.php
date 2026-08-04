<?php

declare(strict_types=1);

namespace He4rt\Venue\Database\Factories\Spot;

use He4rt\Venue\Spot\Enums\OrderSide;
use He4rt\Venue\Spot\Enums\OrderStatus;
use He4rt\Venue\Spot\Models\SpotOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpotOrder> */
class SpotOrderFactory extends Factory
{
    protected $model = SpotOrder::class;

    public function definition(): array
    {
        return [
            'order_id' => fake()->unique()->numberBetween(1_000_000, 999_999_999),
            'client_order_id' => fake()->unique()->uuid(),
            'symbol' => 'USDCBRL',
            'side' => OrderSide::Buy,
            'type' => 'MARKET',
            'status' => OrderStatus::Filled,
            'quantity' => null,
            'quote_order_qty' => '15',
            'executed_qty' => '2.93000000',
            'cummulative_quote_qty' => '14.97230000',
            'fill_price' => '5.11',
            'commission' => '0.00293000',
            'commission_asset' => 'USDC',
        ];
    }

    /**
     * Preencheu parte da quantidade e expirou — MARKET que não conseguiu
     * casar o total (o remanescente não é reenviado, {@see OrderStatus::Expired}).
     */
    public function partiallyFilledThenExpired(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Expired,
            'executed_qty' => '1.50000000',
            'cummulative_quote_qty' => '7.66500000',
            'commission' => '0.00150000',
        ]);
    }

    /**
     * Recusada pela venue antes de qualquer execução — sem fill.
     */
    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Rejected,
            'executed_qty' => '0',
            'cummulative_quote_qty' => '0',
            'fill_price' => null,
            'commission' => '0',
            'commission_asset' => null,
        ]);
    }
}
