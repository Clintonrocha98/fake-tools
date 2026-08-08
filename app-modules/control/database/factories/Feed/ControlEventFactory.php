<?php

declare(strict_types=1);

namespace He4rt\Control\Database\Factories\Feed;

use He4rt\Control\Feed\DTOs\ControlEventContext;
use He4rt\Control\Feed\Models\ControlEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/** @extends Factory<ControlEvent> */
class ControlEventFactory extends Factory
{
    protected $model = ControlEvent::class;

    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(['binance', 'starkbank']),
            'level' => 'info',
            'message' => fake()->sentence(),
            'context' => ControlEventContext::empty(),
            'request_id' => null,
            'occurred_at' => Date::now(),
        ];
    }

    public function binance(): self
    {
        return $this->state(fn (): array => ['channel' => 'binance']);
    }

    public function starkbank(): self
    {
        return $this->state(fn (): array => ['channel' => 'starkbank']);
    }
}
