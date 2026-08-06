<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Os símbolos que o fake serve — a fronteira única onde
 * {@see \He4rt\FakeBinance\Spot\Http\Controllers\BookTickerController},
 * {@see \He4rt\FakeBinance\Spot\Http\Controllers\ExchangeInfoController} e
 * {@see \He4rt\FakeBinance\Spot\Http\Controllers\PlaceOrderController} resolvem o
 * `symbol` da wire. Um `symbol` ausente ou desconhecido nunca deve cair no
 * config de um par servido por padrão — isso inventaria dados para um par que
 * o fake não serve.
 */
enum SpotSymbol: string implements HasColor, HasDescription, HasLabel
{
    case UsdcBrl = 'USDCBRL';
    case UsdtBrl = 'USDTBRL';

    public static function tryFromWire(mixed $symbol): ?self
    {
        return is_string($symbol) ? self::tryFrom($symbol) : null;
    }

    /**
     * A config deste caso em `fake-binance-spot.symbols` — todo leitor de
     * preço/precisão/filtros resolve por aqui, nunca por uma chave literal.
     *
     * @return array<array-key, mixed>
     */
    public function config(): array
    {
        return config()->array('fake-binance-spot.symbols.'.$this->value);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::UsdcBrl => 'USDC/BRL',
            self::UsdtBrl => 'USDT/BRL',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UsdcBrl => 'info',
            self::UsdtBrl => 'success',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::UsdcBrl => 'Par da escolha USDC do cliente — base USDC, quote BRL',
            self::UsdtBrl => 'Par do asset intermediário do fluxo principal — base USDT, quote BRL',
        };
    }
}
