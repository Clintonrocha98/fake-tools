<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Support;

/**
 * O identificador que as duas pernas fiat expõem na wire — `orderNo` na entrada
 * ({@see \He4rt\FakeBinance\Fiat\Actions\OpenFiatDeposit}) e `orderId` na saída
 * ({@see \He4rt\FakeBinance\Fiat\Actions\RequestFiatWithdrawal}). A venue
 * entrega uma string PURAMENTE NUMÉRICA nos dois campos: um UUID com hífen num
 * campo que a venue nunca hifeniza vaza para regex, índice e coluna de quem
 * consome, e aí o fake vira a fonte de um bug que a venue não produziria.
 */
final readonly class FiatOrderNumber
{
    /**
     * @param  callable(string): bool  $isTaken  colisão é do chamador: cada perna
     *                                           tem sua própria tabela e sua própria coluna única.
     */
    public static function generate(callable $isTaken): string
    {
        do {
            $candidate = sprintf('%013d%03d', now()->getTimestampMs() % 10_000_000_000_000, random_int(0, 999));
        } while ($isTaken($candidate));

        return $candidate;
    }
}
