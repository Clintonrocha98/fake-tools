<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Onde o BR Code viaja dentro do `data` de GET /sapi/v1/fiat/get-order-detail.
 * A doc não documenta o campo, então nenhuma das duas posições é "a certa" — o
 * consumidor se protege com uma varredura RECURSIVA, e é ela que este enum
 * existe para exercitar: {@see self::Root} deixa o brcode na raiz (a única
 * posição que a rede rasa alcança), {@see self::Ext} o esconde dentro do `ext`
 * (OBJECT), o ramo que a Binance real provavelmente usa e que o fake nunca
 * exercitava.
 */
enum BrcodePlacement: string implements HasColor, HasDescription, HasLabel
{
    case Root = 'root';
    case Ext = 'ext';

    public function getLabel(): string
    {
        return match ($this) {
            self::Root => 'Raiz do data',
            self::Ext => 'Aninhado no ext',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Root => 'gray',
            self::Ext => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Root => '`data.pixcode` — default do fake, alcançado pela rede rasa do consumidor',
            self::Ext => '`data.ext.pixCode` e nenhum `pixcode` na raiz — só a varredura recursiva encontra',
        };
    }
}
