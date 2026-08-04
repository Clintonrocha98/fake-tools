<?php

declare(strict_types=1);

namespace He4rt\Venue\Scenarios\Actions;

use He4rt\Venue\Scenarios\Models\ScenarioSwitchboard;

/**
 * Lê o switchboard singleton, criando a linha default (tudo desligado) na
 * primeira leitura — nunca há mais de uma linha nesta tabela.
 */
final readonly class GetScenarioSwitchboard
{
    public function __invoke(): ScenarioSwitchboard
    {
        $existing = ScenarioSwitchboard::query()->first();

        if ($existing instanceof ScenarioSwitchboard) {
            return $existing;
        }

        // Valores explícitos (nunca `create([])`): `create()` devolve o modelo em
        // memória com só os atributos que ele mesmo setou — sem isso, o objeto
        // devolvido não carregaria os DEFAULT que o banco aplicaria na inserção.
        return ScenarioSwitchboard::query()->create([
            'outage_mode' => false,
            'rate_limit_mode' => false,
            'rate_limit_retry_after_seconds' => 30,
            'clock_skew_mode' => false,
        ]);
    }
}
