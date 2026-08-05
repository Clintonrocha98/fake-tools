<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * O único símbolo que o fake serve — a fronteira única onde
 * {@see \He4rt\FakeBinance\Spot\Http\Controllers\BookTickerController},
 * {@see \He4rt\FakeBinance\Spot\Http\Controllers\ExchangeInfoController} e
 * {@see \He4rt\FakeBinance\Spot\Http\Controllers\PlaceOrderController} resolvem o
 * `symbol` da wire. Um `symbol` ausente ou desconhecido nunca deve cair no
 * config de `USDCBRL` por padrão — isso inventaria dados para um par que o
 * fake não serve.
 */
enum SpotSymbol: string implements HasColor, HasDescription, HasLabel
{
    case UsdcBrl = 'USDCBRL';

    public static function tryFromWire(mixed $symbol): ?self
    {
        return is_string($symbol) ? self::tryFrom($symbol) : null;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::UsdcBrl => 'USDC/BRL',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UsdcBrl => 'info',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::UsdcBrl => 'Único par servido pelo fake — base USDC, quote BRL',
        };
    }
}
