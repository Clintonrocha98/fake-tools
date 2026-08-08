<?php

declare(strict_types=1);

namespace He4rt\Control\State\Support;

use Carbon\CarbonInterface;
use He4rt\Control\State\DTOs\AdvanceProjection;
use Illuminate\Support\Facades\Date;

/**
 * O relógio do avanço lazy, e SÓ o relógio: idade contra o limiar configurado.
 * A máquina de estados de cada perna fica onde está — replicá-la aqui criaria
 * uma segunda verdade que diverge da primeira no dia em que uma transição
 * mudar.
 */
final readonly class AdvanceClock
{
    public static function project(?CarbonInterface $desde, int $advanceSeconds, ?string $bloqueio = null): AdvanceProjection
    {
        if ($bloqueio !== null) {
            return AdvanceProjection::blocked($bloqueio);
        }

        if (!$desde instanceof CarbonInterface || $advanceSeconds <= 0) {
            return AdvanceProjection::inSeconds(0);
        }

        $idade = (int) $desde->diffInSeconds(Date::now());

        return AdvanceProjection::inSeconds($advanceSeconds - $idade);
    }

    /**
     * `config()->integer()` exigiria um int estrito e explodiria na
     * numeric-string que `env()` produz de um `.env` real.
     */
    public static function seconds(string $chave, int $default = 60): int
    {
        return (int) config($chave, $default);
    }
}
