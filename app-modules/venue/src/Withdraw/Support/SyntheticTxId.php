<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Support;

use Illuminate\Support\Str;

/**
 * Gera o `txId` sintético anexado a um withdraw ao alcançar
 * {@see \He4rt\Venue\Withdraw\Enums\WithdrawStatus::Completed} — mesmo formato
 * (`0x` + 64 hex minúsculos) usado tanto pelo avanço lazy quanto pelo cenário
 * "completar agora" do painel.
 */
final readonly class SyntheticTxId
{
    public static function generate(): string
    {
        return '0x'.Str::lower(Str::random(64));
    }
}
