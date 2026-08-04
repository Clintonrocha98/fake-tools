<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Qual vocabulário de string a wire de GET /sapi/v1/fiat/get-order-detail fala —
 * a Binance real responde os dois, dependendo do momento/endpoint. `Live` é o
 * default do fake (SCREAMING_SNAKE observado ao vivo 02/ago/2026); `Classic` é o
 * dialeto documentado publicamente. Ver {@see FiatOrderStatus::toWire()}.
 */
enum FiatStatusDialect: string implements HasColor, HasDescription, HasLabel
{
    case Live = 'live';
    case Classic = 'classic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Live => 'Live (ORDER_*)',
            self::Classic => 'Clássico (doc pública)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Live => 'primary',
            self::Classic => 'gray',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Live => 'SCREAMING_SNAKE observado ao vivo (ORDER_PROCESSING, ORDER_SUCCESS, …) — default do fake',
            self::Classic => 'Strings documentadas publicamente (Processing, Successful, …)',
        };
    }
}
