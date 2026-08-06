<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;

/**
 * Leitura sem consumo — a UI precisa mostrar o que está armado sem gastar o
 * cenário. Só quem executa o pedido consome
 * ({@see ConsumeArmedScenario}).
 */
final readonly class GetArmedScenario
{
    public function handle(VenueLeg $leg): ?ArmedScenario
    {
        return ArmedScenario::query()->where('leg', $leg)->first();
    }
}
