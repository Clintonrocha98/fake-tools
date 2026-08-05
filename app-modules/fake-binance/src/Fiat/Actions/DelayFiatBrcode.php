<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Actions;

use He4rt\FakeBinance\Fiat\Models\FiatOrder;

/**
 * Cenário do painel: atrasa o brcode por `$reads` releituras de
 * get-order-detail — mesma semântica do `$brcodeAfterReads` do fake de teste
 * do monolito consumidor (0 = brcode já na primeira leitura). Zera
 * `brcode_reads_count` para que o atraso conte a partir de agora, nunca das
 * leituras que já aconteceram antes deste comando.
 */
final readonly class DelayFiatBrcode
{
    public function handle(FiatOrder $order, ?int $reads): FiatOrder
    {
        $order->update([
            'brcode_delay_reads' => $reads,
            'brcode_reads_count' => 0,
        ]);

        return $order->refresh();
    }
}
