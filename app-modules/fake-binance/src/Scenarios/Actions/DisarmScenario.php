<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Support\BinanceLog;

/**
 * Desarma a perna. Desarmar o que não estava armado é no-op silencioso — o
 * estado final é o mesmo, e um erro aqui só atrapalharia a UI.
 */
final readonly class DisarmScenario
{
    public function handle(VenueLeg $leg): void
    {
        $removidos = ArmedScenario::query()->where('leg', $leg)->delete();

        if ($removidos > 0) {
            BinanceLog::info('fake-binance.scenarios: perna desarmada sob comando — o desvio armado morre sem ser consumido e o próximo pedido volta ao happy path', [
                'leg' => $leg->value,
            ]);
        }
    }
}
