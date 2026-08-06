<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Support;

/**
 * O formato de id de todo recurso do StarkBank: string numérica de 16 dígitos
 * (`5155165527080960`). Vale para invoice, transfer, brcode-payment, event e
 * log — por isso mora no Support do módulo, e não dentro de um subsistema.
 *
 * A unicidade real é da coluna (pk ou índice único); este gerador só precisa
 * não colidir na prática.
 */
final readonly class NumericId
{
    public static function generate(): string
    {
        return (string) random_int(1_000_000_000_000_000, 9_999_999_999_999_999);
    }
}
