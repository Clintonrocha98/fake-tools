<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;

/**
 * Desarma a perna. Desarmar o que não estava armado é no-op silencioso — o
 * estado final é o mesmo, e um erro aqui só atrapalharia a UI.
 */
final readonly class DisarmScenario
{
    public function handle(VenueLeg $leg): void
    {
        ArmedScenario::query()->where('leg', $leg)->delete();
    }
}
