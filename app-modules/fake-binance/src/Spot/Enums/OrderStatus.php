<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Vocabulário COMPLETO de status de uma ordem Spot, exatamente como a Binance
 * nomeia na wire — o `fromWire()` do monolito consumidor falha loud num valor
 * fora deste conjunto, então este enum nunca inventa um caso novo.
 */
enum OrderStatus: string implements HasColor, HasDescription, HasLabel
{
    case New = 'NEW';
    case PartiallyFilled = 'PARTIALLY_FILLED';
    case Filled = 'FILLED';
    case Canceled = 'CANCELED';
    case PendingCancel = 'PENDING_CANCEL';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case ExpiredInMatch = 'EXPIRED_IN_MATCH';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nova',
            self::PartiallyFilled => 'Parcialmente preenchida',
            self::Filled => 'Preenchida',
            self::Canceled => 'Cancelada',
            self::PendingCancel => 'Cancelamento pendente',
            self::Rejected => 'Rejeitada',
            self::Expired => 'Expirada',
            self::ExpiredInMatch => 'Expirada em match',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New, self::PendingCancel => 'gray',
            self::PartiallyFilled => 'warning',
            self::Filled => 'success',
            self::Canceled => 'gray',
            self::Rejected, self::Expired, self::ExpiredInMatch => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::New => 'Aceita, ainda não preenchida',
            self::PartiallyFilled => 'Parte da quantidade foi executada',
            self::Filled => 'Totalmente executada ao preço do book',
            self::Canceled => 'Cancelada antes de qualquer execução',
            self::PendingCancel => 'Cancelamento em andamento',
            self::Rejected => 'Recusada pela venue antes de qualquer execução',
            self::Expired => 'MARKET que não preencheu totalmente e não é reenviada',
            self::ExpiredInMatch => 'Expirada durante o matching (ex.: STP)',
        };
    }
}
