<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Http\Errors;

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

    /**
     * O prefixo fiat é versionado (`sapi/v1/fiat/deposit`, `sapi/v1/fiat/get-order-detail`,
     * mas `sapi/v2/fiat/withdraw`) — nunca fixar a versão no match, ou um novo endpoint
     * fiat em outra versão cai silenciosamente na família SpotWallet errada.
     */
    public static function fromPath(string $path): self
    {
        return preg_match('#^sapi/v\d+/fiat/#', $path) === 1 ? self::Fiat : self::SpotWallet;
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
            self::Fiat => 'Envelope {code: string, message: string, success: bool, data: null} — sapi/v{n}/fiat/*',
        };
    }
}
