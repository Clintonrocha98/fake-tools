<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Actions;

use He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard;

/**
 * Lê o switchboard singleton, criando a linha default (tudo desligado) na
 * primeira leitura — nunca há mais de uma linha nesta tabela.
 *
 * O singleton é garantido pela chave primária: a linha vive sempre sob
 * {@see self::SINGLETON_ID}, então dois pollers concorrentes batendo aqui antes
 * de qualquer linha existir não produzem duas linhas — `firstOrCreate()` tenta o
 * `create()` e, se perder a corrida, recupera a violação de unicidade e relê a
 * mesma linha ({@see \Illuminate\Database\Eloquent\Builder::createOrFirst()}).
 */
final readonly class GetScenarioSwitchboard
{
    public const string SINGLETON_ID = '00000000-0000-0000-0000-000000000002';

    public function handle(): ScenarioSwitchboard
    {
        // Valores explícitos (nunca `firstOrCreate($id, [])`): sem isso, o objeto
        // devolvido não carregaria os DEFAULT que o banco aplicaria na inserção.
        return ScenarioSwitchboard::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            [
                'outage_mode' => false,
                'rate_limit_mode' => false,
                'rate_limit_retry_after_seconds' => 30,
            ],
        );
    }
}
