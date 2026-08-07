<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Actions;

use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Support\StarkbankLog;

/**
 * Desarma a perna. Desarmar o que não estava armado é no-op silencioso — o
 * estado final é o mesmo, e um erro aqui só atrapalharia a UI.
 */
final readonly class DisarmScenario
{
    public function handle(PixLeg $leg): void
    {
        $removidos = ArmedScenario::query()->where('leg', $leg)->delete();

        if ($removidos > 0) {
            StarkbankLog::info('fake-starkbank.scenarios: perna desarmada sob comando — o desvio armado morre sem ser consumido e o próximo pedido volta ao happy path', [
                'leg' => $leg->value,
            ]);
        }
    }
}
