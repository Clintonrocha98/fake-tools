<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * O estado canônico interno de uma FiatOrder — o que o fake efetivamente
 * PERSISTE em `status`/`forced_status`. O avanço lazy só percorre
 * `Processing -> Success` pela idade da ordem; todo estado de falha
 * (`Failed`, `Expired`, `Cancelled`, `Refunding`, `Refunded`,
 * `NeedAdditionalAction`) só é alcançado via override (`forced_status`,
 * painel #7). {@see self::toWire()} serializa cada caso nos dois dialetos
 * que a Binance real fala (doc-map ADR/ticket #4).
 */
enum FiatOrderStatus: string implements HasColor, HasDescription, HasLabel
{
    case Processing = 'processing';
    case NeedAdditionalAction = 'need_additional_action';
    case Success = 'success';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunding = 'refunding';
    case Refunded = 'refunded';

    public function toWire(FiatStatusDialect $dialect): string
    {
        return match ($dialect) {
            FiatStatusDialect::Live => match ($this) {
                self::Processing => 'ORDER_PROCESSING',
                self::NeedAdditionalAction => 'ORDER_NEED_ADDITIONAL_ACTION',
                self::Success => 'ORDER_SUCCESS',
                self::Failed => 'ORDER_FAILED',
                self::Expired => 'ORDER_EXPIRED',
                self::Cancelled => 'ORDER_CANCELLED',
                self::Refunding => 'ORDER_REFUNDING',
                self::Refunded => 'ORDER_REFUNDED',
            },
            FiatStatusDialect::Classic => match ($this) {
                // A doc pública não define uma palavra própria para "ação
                // necessária" — cai no bucket mais próximo (`Processing`).
                self::Processing, self::NeedAdditionalAction => 'Processing',
                self::Success => 'Successful',
                self::Failed, self::Cancelled => 'Failed',
                self::Expired => 'Expired',
                self::Refunding => 'Refunding',
                self::Refunded => 'Refunded',
            },
        };
    }

    public function isCredited(): bool
    {
        return $this === self::Success;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Processing => 'Processando',
            self::NeedAdditionalAction => 'Ação adicional necessária',
            self::Success => 'Creditado',
            self::Failed => 'Falhou',
            self::Expired => 'Expirado',
            self::Cancelled => 'Cancelado',
            self::Refunding => 'Estornando',
            self::Refunded => 'Estornado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Processing => 'gray',
            self::NeedAdditionalAction => 'warning',
            self::Success => 'success',
            self::Failed, self::Expired, self::Cancelled => 'danger',
            self::Refunding => 'warning',
            self::Refunded => 'gray',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Processing => 'Ordem aberta, aguardando o avanço lazy pela idade',
            self::NeedAdditionalAction => 'A venue aguarda uma ação fora da API',
            self::Success => 'Ordem creditada no ledger (BRL)',
            self::Failed => 'Ordem morreu — nunca creditada',
            self::Expired => 'Ordem expirou antes de ser creditada',
            self::Cancelled => 'Ordem cancelada — nunca creditada',
            self::Refunding => 'Estorno em andamento',
            self::Refunded => 'Valor estornado ao remetente',
        };
    }
}
