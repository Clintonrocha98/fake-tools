<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Os dois switches globais que derrubam qualquer rota `/v2/*` deste fake — cada
 * um mapeia para uma coluna de
 * {@see \He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard}.
 * {@see \He4rt\FakeStarkbank\Scenarios\Http\Middleware\ApplyPixScenarioSwitches}
 * os verifica nesta mesma ordem (Outage vence RateLimit) antes de qualquer
 * endpoint.
 *
 * Não há análogo a `clock_skew` aqui: a janela de recepção do StarkBank já é
 * exercitada pelo próprio `Access-Time` que o consumidor envia.
 */
enum PixScenarioSwitch: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Outage = 'outage';
    case RateLimit = 'rate_limit';

    public function column(): string
    {
        return match ($this) {
            self::Outage => 'outage_mode',
            self::RateLimit => 'rate_limit_mode',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Outage => 'Modo outage',
            self::RateLimit => 'Modo rate limit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Outage => 'danger',
            self::RateLimit => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Outage => 'Toda rota /v2/* responde HTTP 503 com o envelope internalServerError',
            self::RateLimit => 'Toda rota /v2/* responde 429 (tooManyRequests) + header Retry-After',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Outage => Heroicon::OutlinedSignalSlash,
            self::RateLimit => Heroicon::OutlinedClock,
        };
    }
}
