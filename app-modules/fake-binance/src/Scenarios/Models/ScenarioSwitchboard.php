<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Models;

use App\Models\BaseModel;
use He4rt\FakeBinance\Database\Factories\Scenarios\ScenarioSwitchboardFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * Linha única (singleton) com os switches globais que a camada HTTP consulta
 * ANTES de qualquer endpoint ({@see \He4rt\FakeBinance\Scenarios\Http\Middleware\ApplyScenarioSwitches}).
 * Persistida em banco — sobrevive a restart, ao contrário de um flag em memória.
 *
 * @property string $id
 * @property bool $outage_mode
 * @property bool $rate_limit_mode
 * @property int $rate_limit_retry_after_seconds
 * @property bool $clock_skew_mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<ScenarioSwitchboardFactory>
 */
#[UseFactory(factoryClass: ScenarioSwitchboardFactory::class)]
#[Table(name: 'fake_binance_scenario_switches')]
final class ScenarioSwitchboard extends BaseModel
{
    protected function casts(): array
    {
        return [
            'outage_mode' => 'boolean',
            'rate_limit_mode' => 'boolean',
            'clock_skew_mode' => 'boolean',
        ];
    }
}
