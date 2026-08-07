<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard;
use He4rt\FakeBinance\Support\BinanceLog;

/**
 * Liga/desliga um switch global no singleton — persistido em banco, então o
 * estado sobrevive a restart do processo.
 */
final readonly class ToggleScenarioSwitch
{
    public function __construct(private GetScenarioSwitchboard $get = new GetScenarioSwitchboard) {}

    public function handle(ScenarioSwitch $switch, bool $enabled): ScenarioSwitchboard
    {
        $switchboard = $this->get->handle();

        $switchboard->update([$switch->column() => $enabled]);

        BinanceLog::info('fake-binance.scenarios: switch global alterado — vale para toda rota deste fake e para nenhuma do fake-starkbank, que tem switchboard próprio', [
            'switch' => $switch->value,
            'enabled' => $enabled,
        ]);

        return $switchboard->refresh();
    }
}
