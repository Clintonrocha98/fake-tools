<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Ledger\Support;

/**
 * Parseia o formato "ASSET:AMOUNT,ASSET:AMOUNT" de FAKE_BINANCE_SEED_BALANCES. Pares
 * malformados (sem ':', asset vazio, amount não numérico) são silenciosamente
 * ignorados — um seeder de dev não deve derrubar a aplicação por uma env mal digitada.
 * O código do ativo é devolvido como veio: normalizar o case é responsabilidade única
 * de {@see \He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount}, a fronteira do ledger.
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
            if ($pair === '') {
                continue;
            }

            if (!str_contains($pair, ':')) {
                continue;
            }

            [$asset, $amount] = array_map(trim(...), explode(':', $pair, 2));
            if ($asset === '') {
                continue;
            }

            if (!is_numeric($amount)) {
                continue;
            }

            $balances[$asset] = $amount;
        }

        return $balances;
    }
}
