<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Database\Factories\Scenarios;

use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/** @extends Factory<ArmedScenario> */
class ArmedScenarioFactory extends Factory
{
    protected $model = ArmedScenario::class;

    public function definition(): array
    {
        return [
            'leg' => VenueLeg::SpotConversion,
            'outcome' => SpotConversionOutcome::RespondRejected->value,
            'payload' => ArmedScenarioPayload::empty(),
            'armed_at' => Date::now(),
        ];
    }

    /**
     * Conversão armada para preencher metade e expirar o resto.
     */
    public function spotPartial(): static
    {
        return $this->state(fn (): array => [
            'leg' => VenueLeg::SpotConversion,
            'outcome' => SpotConversionOutcome::FillPartialExpired->value,
            'payload' => new ArmedScenarioPayload(fraction: '0.5'),
        ]);
    }
}
