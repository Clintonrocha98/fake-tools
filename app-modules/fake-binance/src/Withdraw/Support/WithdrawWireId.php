<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Withdraw\Support;

/**
 * O `id` de um withdraw como a venue o entrega: 32 caracteres hex, sem hífen.
 * A PK continua sendo o UUID v7 do banco — tirar os hífens de um UUID dá
 * exatamente 32 hex, então a conversão é reversível e o id da wire e o do
 * registro são o MESMO valor em dois formatos. Ver ADR-0005.
 */
final readonly class WithdrawWireId
{
    public static function for(string $withdrawalId): string
    {
        return str_replace('-', '', $withdrawalId);
    }
}
