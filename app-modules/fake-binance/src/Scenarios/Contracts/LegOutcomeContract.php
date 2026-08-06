<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Contracts;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;

/**
 * Um desfecho armável. Cada perna declara os seus num enum próprio — os
 * desfechos de uma perna não valem para outra, e é `leg()` que amarra cada
 * desfecho à sua, tanto para persistir quanto para montar a UI.
 */
interface LegOutcomeContract extends BackedEnum, HasColor, HasDescription, HasLabel
{
    public function leg(): VenueLeg;

    /**
     * Os campos de {@see \He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload}
     * que este desfecho usa — a UI só mostra estes, e armar com qualquer outro
     * é ruído descartado.
     *
     * @return list<string>
     */
    public function payloadFields(): array;
}
