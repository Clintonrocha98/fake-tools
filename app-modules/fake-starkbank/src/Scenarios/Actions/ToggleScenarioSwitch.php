<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Actions;

use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard;
use He4rt\FakeStarkbank\Support\StarkbankLog;

/**
 * Liga/desliga um switch global no singleton — persistido em banco, então o
 * estado sobrevive a restart do processo.
 */
final readonly class ToggleScenarioSwitch
{
    public function __construct(private GetScenarioSwitchboard $get = new GetScenarioSwitchboard) {}

    public function handle(PixScenarioSwitch $switch, bool $enabled): ScenarioSwitchboard
    {
        $switchboard = $this->get->handle();

        $switchboard->update([$switch->column() => $enabled]);

        StarkbankLog::info('fake-starkbank.scenarios: switch global alterado — vale para toda rota /v2/* deste fake e para nenhuma do fake-binance, que tem switchboard próprio', [
            'switch' => $switch->value,
            'enabled' => $enabled,
        ]);

        return $switchboard->refresh();
    }
}
