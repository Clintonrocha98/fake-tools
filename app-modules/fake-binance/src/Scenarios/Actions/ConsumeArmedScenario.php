<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Support\Facades\DB;

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
    public function handle(VenueLeg $leg): ?ArmedScenario
    {
        return DB::transaction(function () use ($leg): ?ArmedScenario {
            $armed = ArmedScenario::query()
                ->where('leg', $leg)
                ->lockForUpdate()
                ->first();

            $armed?->delete();

            return $armed;
        });
    }
}
