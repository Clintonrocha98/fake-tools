<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Support;

/**
 * Parseia o formato "ASSET:AMOUNT,ASSET:AMOUNT" de FAKE_BINANCE_SEED_BALANCES. Pares
 * malformados (sem ':', asset vazio, amount não numérico) são silenciosamente
 * ignorados — um seeder de dev não deve derrubar a aplicação por uma env mal digitada.
 */
final class SeedBalancesParser
{
    /**
     * @return array<string, string>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || mb_trim($raw) === '') {
            return [];
        }

        $balances = [];

        foreach (explode(',', $raw) as $pair) {
            $pair = mb_trim($pair);

            if ($pair === '' || !str_contains($pair, ':')) {
                continue;
            }

            [$asset, $amount] = array_map('trim', explode(':', $pair, 2));

            if ($asset === '' || !is_numeric($amount)) {
                continue;
            }

            $balances[mb_strtoupper($asset)] = $amount;
        }

        return $balances;
    }
}
