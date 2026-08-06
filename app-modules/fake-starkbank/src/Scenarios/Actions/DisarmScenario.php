<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Actions;

use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;

/**
 * Desarma a perna. Desarmar o que não estava armado é no-op silencioso — o
 * estado final é o mesmo, e um erro aqui só atrapalharia a UI.
 */
final readonly class DisarmScenario
{
    public function handle(PixLeg $leg): void
    {
        ArmedScenario::query()->where('leg', $leg)->delete();
    }
}
