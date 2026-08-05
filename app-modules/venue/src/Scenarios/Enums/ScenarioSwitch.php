<?php

declare(strict_types=1);

namespace He4rt\Venue\Scenarios\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Os três switches globais que exercitam o `BinanceErrorBoundary` do monolito
 * consumidor — cada um mapeia para uma coluna de
 * {@see \He4rt\Venue\Scenarios\Models\ScenarioSwitchboard}.
 * {@see \He4rt\Venue\Scenarios\Http\Middleware\ApplyScenarioSwitches} os
 * verifica nesta mesma ordem (Outage vence RateLimit vence ClockSkew) antes de
 * qualquer endpoint.
 */
enum ScenarioSwitch: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Outage = 'outage';
    case RateLimit = 'rate_limit';
    case ClockSkew = 'clock_skew';

    public function column(): string
    {
        return match ($this) {
            self::Outage => 'outage_mode',
            self::RateLimit => 'rate_limit_mode',
            self::ClockSkew => 'clock_skew_mode',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Outage => 'Modo outage',
            self::RateLimit => 'Modo rate limit',
            self::ClockSkew => 'Modo relógio torto',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Outage => 'danger',
            self::RateLimit => 'warning',
            self::ClockSkew => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Outage => 'Todo endpoint responde HTTP 5xx',
            self::RateLimit => 'Todo endpoint responde 429 + Retry-After',
            self::ClockSkew => 'Todo endpoint recusa com -1021 (timestamp fora da janela)',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Outage => Heroicon::OutlinedSignalSlash,
            self::RateLimit => Heroicon::OutlinedClock,
            self::ClockSkew => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
