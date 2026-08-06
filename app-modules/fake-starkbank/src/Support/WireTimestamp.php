<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * O formato de data de toda a wire do StarkBank: ISO-8601 em UTC com
 * microssegundos e offset explícito (`2026-07-09T12:00:00.762238+00:00`), o dos
 * fixtures do consumidor.
 *
 * Um fake que serve dois formatos conforme a origem do valor esconde drift de
 * contrato — por isso todo timestamp sai por aqui, mesmo os que o consumidor
 * hoje só repassa a `Carbon::parse()`.
 */
final readonly class WireTimestamp
{
    public static function format(?CarbonInterface $at): string
    {
        return CarbonImmutable::parse($at ?? CarbonImmutable::now())
            ->utc()
            ->format('Y-m-d\TH:i:s.uP');
    }
}
