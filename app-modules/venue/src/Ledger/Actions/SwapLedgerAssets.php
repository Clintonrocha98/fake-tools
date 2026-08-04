<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Actions;

use He4rt\Venue\Ledger\DTOs\LedgerFill;
use Illuminate\Support\Facades\DB;

/**
 * Traduz uma execução MARKET (uma lista de fills) numa troca coerente no ledger: soma
 * o total gasto em `from` e o total recebido em `to` através de todos os fills, aplica a
 * comissão do lado indicado por `commissionAsset`, e debita/credita como uma única
 * transação — nunca fill a fill, para não expor um estado intermediário inconsistente.
 */
final readonly class SwapLedgerAssets
{
    public function __construct(
        private CreditLedgerAccount $credit,
        private DebitLedgerAccount $debit,
    ) {}

    /**
     * @param  list<LedgerFill>  $fills
     */
    public function __invoke(string $from, string $to, array $fills): void
    {
        DB::transaction(function () use ($from, $to, $fills): void {
            [$spent, $received] = $this->totals($from, $to, $fills);

            ($this->debit)($from, $spent);
            ($this->credit)($to, $received);
        });
    }

    /**
     * @param  list<LedgerFill>  $fills
     * @return array{0: numeric-string, 1: numeric-string}
     */
    private function totals(string $from, string $to, array $fills): array
    {
        $spent = '0';
        $received = '0';

        foreach ($fills as $fill) {
            $spent = bcadd($spent, bcmul($fill->qty, $fill->price, 18), 18);
            $received = bcadd($received, $fill->qty, 18);

            if ($fill->commissionAsset === $to) {
                $received = bcsub($received, $fill->commission, 18);
            } elseif ($fill->commissionAsset === $from) {
                $spent = bcadd($spent, $fill->commission, 18);
            }
        }

        return [$spent, $received];
    }
}
