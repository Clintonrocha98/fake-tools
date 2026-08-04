<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Actions;

use He4rt\Venue\Ledger\DTOs\LedgerFill;
use He4rt\Venue\Ledger\Enums\Side;
use Illuminate\Support\Facades\DB;

/**
 * Traduz uma execução MARKET (uma lista de fills) numa troca coerente no ledger: soma
 * o total gasto em `from` e o total recebido em `to` através de todos os fills, aplica a
 * comissão do lado indicado por `commissionAsset`, e debita/credita como uma única
 * transação — nunca fill a fill, para não expor um estado intermediário inconsistente.
 *
 * `qty` é sempre a quantidade BASE do fill e `qty * price` é sempre o total QUOTE — em
 * BUY `from` é a quote (spent = qty*price) e `to` é a base (received = qty); em SELL é
 * o inverso, espelhando `Execution::toConversionResult()` do consumidor.
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
    public function __invoke(string $from, string $to, array $fills, Side $side): void
    {
        DB::transaction(function () use ($from, $to, $fills, $side): void {
            [$spent, $received] = $this->totals($from, $to, $fills, $side);

            ($this->debit)($from, $spent);
            ($this->credit)($to, $received);
        });
    }

    /**
     * @param  list<LedgerFill>  $fills
     * @return array{0: numeric-string, 1: numeric-string}
     */
    private function totals(string $from, string $to, array $fills, Side $side): array
    {
        $baseTotal = '0';
        $quoteTotal = '0';

        foreach ($fills as $fill) {
            $baseTotal = bcadd($baseTotal, $fill->qty, 18);
            $quoteTotal = bcadd($quoteTotal, bcmul($fill->qty, $fill->price, 18), 18);
        }

        // BUY: from=quote (spent), to=base (received). SELL: from=base (spent), to=quote
        // (received) — mesma direção que `Execution::toConversionResult()` do consumidor.
        [$spent, $received] = $side === Side::Buy
            ? [$quoteTotal, $baseTotal]
            : [$baseTotal, $quoteTotal];

        foreach ($fills as $fill) {
            if ($fill->commissionAsset === $to) {
                $received = bcsub($received, $fill->commission, 18);
            } elseif ($fill->commissionAsset === $from) {
                $spent = bcadd($spent, $fill->commission, 18);
            }
        }

        return [$spent, $received];
    }
}
