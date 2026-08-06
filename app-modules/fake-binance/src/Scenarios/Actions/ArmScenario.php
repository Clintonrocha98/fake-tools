<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Support\Facades\Date;

/**
 * Arma o desfecho do próximo pedido da perna, substituindo o que já estivesse
 * armado ali — dois desfechos para o mesmo próximo pedido se contradizem, e só
 * o mais recente vale.
 *
 * `updateOrCreate()` resolve a substituição numa operação só, e é seguro sob
 * duas armadas concorrentes na mesma perna: se ambas encontram a perna
 * desarmada e disputam o `INSERT`, a perdedora esbarra no índice único de
 * `leg`, mas `createOrFirst()` recupera essa violação e relê a linha que a
 * vencedora acabou de criar — a perdedora então aplica o seu desfecho como um
 * `UPDATE` sobre essa linha, e nenhuma `QueryException` escapa para quem chamou.
 */
final readonly class ArmScenario
{
    public function handle(LegOutcomeContract $outcome, ArmedScenarioPayload $payload = new ArmedScenarioPayload): ArmedScenario
    {
        return ArmedScenario::query()->updateOrCreate(
            ['leg' => $outcome->leg()],
            [
                'outcome' => (string) $outcome->value,
                'payload' => $payload,
                'armed_at' => Date::now(),
            ],
        );
    }
}
