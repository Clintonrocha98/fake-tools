<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Arma o desfecho do próximo pedido da perna. Armar de novo SUBSTITUI: dois
 * desfechos para o mesmo próximo pedido se contradizem, então o anterior sai
 * antes do novo entrar, na mesma transação.
 */
final readonly class ArmScenario
{
    public function handle(LegOutcomeContract $outcome, ArmedScenarioPayload $payload = new ArmedScenarioPayload): ArmedScenario
    {
        $leg = $outcome->leg();

        return DB::transaction(function () use ($leg, $outcome, $payload): ArmedScenario {
            ArmedScenario::query()->where('leg', $leg)->delete();

            return ArmedScenario::query()->create([
                'leg' => $leg,
                'outcome' => (string) $outcome->value,
                'payload' => $payload,
                'armed_at' => Date::now(),
            ]);
        });
    }
}
