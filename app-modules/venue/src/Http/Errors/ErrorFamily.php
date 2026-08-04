<?php

declare(strict_types=1);

namespace He4rt\Venue\Http\Errors;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * A Binance real fala dois "dialetos" de erro conforme a família do endpoint:
 * spot/wallet ({@see self::SpotWallet}) e fiat ({@see self::Fiat}). A família é
 * decidida pelo prefixo do path — nunca pelo código do erro, que é o mesmo em
 * ambas, apenas serializado de forma diferente.
 */
enum ErrorFamily: string implements HasColor, HasDescription, HasLabel
{
    case SpotWallet = 'spot_wallet';
    case Fiat = 'fiat';

    public static function fromPath(string $path): self
    {
        return str_starts_with($path, 'sapi/v1/fiat/') ? self::Fiat : self::SpotWallet;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::SpotWallet => 'Spot / Wallet',
            self::Fiat => 'Fiat',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SpotWallet => 'info',
            self::Fiat => 'primary',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SpotWallet => 'Envelope {code: int negativo, msg: string} — /api/*, /sapi/v1/capital/*',
            self::Fiat => 'Envelope {code: string, message: string, data: null} — /sapi/v1/fiat/*',
        };
    }
}
