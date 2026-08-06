<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Actions;

use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consome o cenário armado da perna: devolve o que estava armado e apaga a
 * linha, de forma que o pedido seguinte já veja o happy path.
 *
 * O `lockForUpdate()` é o que faz "vale uma vez" valer sob concorrência: dois
 * pedidos simultâneos serializam na mesma linha e só o primeiro leva o
 * cenário — o segundo encontra a linha já apagada e segue no plano neutro.
 */
final readonly class ConsumeArmedScenario
{
    public function handle(PixLeg $leg): ?ArmedScenario
    {
        $armed = DB::transaction(function () use ($leg): ?ArmedScenario {
            $armed = ArmedScenario::query()
                ->where('leg', $leg)
                ->lockForUpdate()
                ->first();

            $armed?->delete();

            return $armed;
        });

        if ($armed instanceof ArmedScenario) {
            Log::info('fake-starkbank.scenarios: cenário consumido — o desvio vale para este pedido e some, porque um cenário que ficasse de pé viraria o novo comportamento padrão do fake', [
                'leg' => $leg->value,
                'outcome' => $armed->outcome,
            ]);
        }

        return $armed;
    }
}
