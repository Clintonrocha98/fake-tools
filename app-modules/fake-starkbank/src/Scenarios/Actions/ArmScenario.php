<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Actions;

use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

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
    public function handle(PixLegOutcomeContract $outcome, PixScenarioPayload $payload = new PixScenarioPayload): ArmedScenario
    {
        $armed = ArmedScenario::query()->updateOrCreate(
            ['leg' => $outcome->leg()],
            [
                'outcome' => (string) $outcome->value,
                'payload' => $payload,
                'armed_at' => Date::now(),
            ],
        );

        Log::info('fake-starkbank.scenarios: perna armada sob comando — o desvio nasce no próximo pedido dela e some em seguida, para o happy path voltar sozinho', [
            'leg' => $outcome->leg()->value,
            'outcome' => $outcome->value,
            'reason' => $payload->reason,
            'extra_seconds' => $payload->extraSeconds,
        ]);

        return $armed;
    }
}
