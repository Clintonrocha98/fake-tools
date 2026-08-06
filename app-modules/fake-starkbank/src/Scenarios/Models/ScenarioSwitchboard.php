<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Models;

use App\Models\BaseModel;
use He4rt\FakeStarkbank\Database\Factories\Scenarios\ScenarioSwitchboardFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * Linha única (singleton) com os switches globais que a camada HTTP consulta
 * ANTES de qualquer endpoint `/v2/*`
 * ({@see \He4rt\FakeStarkbank\Scenarios\Http\Middleware\ApplyPixScenarioSwitches}).
 * Persistida em banco — sobrevive a restart, ao contrário de um flag em
 * memória.
 *
 * Tabela distinta da do fake-binance de propósito: `outage` ligado aqui não
 * alcança nenhuma rota da venue.
 *
 * @property string $id
 * @property bool $outage_mode
 * @property bool $rate_limit_mode
 * @property int $rate_limit_retry_after_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<ScenarioSwitchboardFactory>
 */
#[UseFactory(factoryClass: ScenarioSwitchboardFactory::class)]
#[Table(name: 'fake_starkbank_scenario_switchboard')]
final class ScenarioSwitchboard extends BaseModel
{
    protected function casts(): array
    {
        return [
            'outage_mode' => 'boolean',
            'rate_limit_mode' => 'boolean',
            'rate_limit_retry_after_seconds' => 'integer',
        ];
    }
}
