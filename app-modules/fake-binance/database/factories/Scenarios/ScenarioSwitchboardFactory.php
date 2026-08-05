<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Database\Factories\Scenarios;

use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScenarioSwitchboard> */
class ScenarioSwitchboardFactory extends Factory
{
    protected $model = ScenarioSwitchboard::class;

    public function definition(): array
    {
        return [
            'outage_mode' => false,
            'rate_limit_mode' => false,
            'rate_limit_retry_after_seconds' => 30,
            'clock_skew_mode' => false,
        ];
    }
}
