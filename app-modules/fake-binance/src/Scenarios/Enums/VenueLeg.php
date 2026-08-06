<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;

/**
 * As pernas da venue que aceitam um cenário armado — uma por família de pedido
 * que o consumidor faz. No máximo um cenário armado por perna, garantido pelo
 * índice único de `leg` em `fake_binance_armed_scenarios`; pernas diferentes
 * ficam armadas ao mesmo tempo sem se atrapalhar.
 */
enum VenueLeg: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case SpotConversion = 'spot_conversion';

    /**
     * @return list<LegOutcomeContract>
     */
    public function outcomes(): array
    {
        return match ($this) {
            self::SpotConversion => SpotConversionOutcome::cases(),
        };
    }

    public function outcomeFrom(string $value): LegOutcomeContract
    {
        return match ($this) {
            self::SpotConversion => SpotConversionOutcome::from($value),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SpotConversion => 'Conversão spot',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SpotConversion => 'primary',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SpotConversion => 'POST /api/v3/order — o desfecho aparece na resposta do próprio pedido',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::SpotConversion => Heroicon::OutlinedArrowsRightLeft,
        };
    }
}
