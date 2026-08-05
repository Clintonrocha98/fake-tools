<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Actions;

use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard;

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

        return $switchboard->refresh();
    }
}
