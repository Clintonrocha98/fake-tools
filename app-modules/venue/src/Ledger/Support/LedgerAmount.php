<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Support;

/**
 * O ledger guarda saldos com escala fixa de 18 casas (precisão para bcmath), mas a
 * Binance nunca expõe zeros à direita — "100000", não "100000.000000000000000000".
 * Esta classe só converte a representação de saída; o valor interno do model permanece
 * na escala cheia para as operações aritméticas.
 */
final class LedgerAmount
{
    public static function wire(string $decimal): string
    {
        if (!str_contains($decimal, '.')) {
            return $decimal;
        }

        $trimmed = mb_rtrim(mb_rtrim($decimal, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
