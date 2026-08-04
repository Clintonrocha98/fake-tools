<?php

declare(strict_types=1);

namespace He4rt\Venue\Spot\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * O lado de uma ordem Spot, no vocabulário da wire da Binance. Em BUY o par
 * gasta o quote asset (BRL) e recebe o base asset (USDC); em SELL é o
 * inverso — mesma convenção de {@see \He4rt\Venue\Ledger\Enums\Side}.
 */
enum OrderSide: string implements HasColor, HasDescription, HasLabel
{
    case Buy = 'BUY';
    case Sell = 'SELL';

    public function getLabel(): string
    {
        return match ($this) {
            self::Buy => 'Compra',
            self::Sell => 'Venda',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Buy => 'success',
            self::Sell => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Buy => 'Gasta o quote asset, recebe o base asset',
            self::Sell => 'Gasta o base asset, recebe o quote asset',
        };
    }
}
