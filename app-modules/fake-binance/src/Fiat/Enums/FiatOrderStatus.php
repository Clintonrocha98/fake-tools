<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * O estado canônico interno de uma FiatOrder — o que o fake efetivamente
 * PERSISTE em `status`/`forced_status`. O avanço lazy só percorre
 * `Processing -> Success` pela idade da ordem; todo estado de falha
 * (`Failed`, `Expired`, `Cancelled`, `Refunding`, `Refunded`, `RefundFailed`,
 * `PartialCreditStopped`, `NeedAdditionalAction`) só é alcançado via override
 * (`forced_status`, painel). {@see self::toWire()} serializa cada caso nos
 * dois dialetos que a Binance real fala — ver ADR-0001. Um vocabulário de
 * wire que o enum não modela (o arm fail-closed do consumidor) passa por
 * `forced_wire_status` em {@see \He4rt\FakeBinance\Fiat\Models\FiatOrder}, fora
 * deste enum.
 */
enum FiatOrderStatus: string implements HasColor, HasDescription, HasLabel
{
    case Processing = 'processing';
    case NeedAdditionalAction = 'need_additional_action';
    case Success = 'success';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunding = 'refunding';
    case Refunded = 'refunded';
    case RefundFailed = 'refund_failed';
    case PartialCreditStopped = 'partial_credit_stopped';

    public function toWire(FiatStatusDialect $dialect): string
    {
        return match ($dialect) {
            FiatStatusDialect::Live => match ($this) {
                self::Processing => 'ORDER_PROCESSING',
                self::NeedAdditionalAction => 'ORDER_NEED_ADDITIONAL_ACTION',
                self::Success => 'ORDER_SUCCESS',
                self::Completed => 'ORDER_COMPLETED',
                self::Failed => 'ORDER_FAILED',
                self::Expired => 'ORDER_EXPIRED',
                self::Cancelled => 'ORDER_CANCELLED',
                self::Refunding => 'ORDER_REFUNDING',
                self::Refunded => 'ORDER_REFUNDED',
                self::RefundFailed => 'ORDER_REFUND_FAILED',
                self::PartialCreditStopped => 'ORDER_PARTIAL_CREDIT_STOPPED',
            },
            FiatStatusDialect::Classic => match ($this) {
                // A doc pública não define uma palavra própria para "ação
                // necessária" — cai no bucket mais próximo (`Processing`).
                self::Processing, self::NeedAdditionalAction => 'Processing',
                self::Success => 'Successful',
                self::Completed => 'Finished',
                self::Failed, self::Cancelled => 'Failed',
                self::Expired => 'Expired',
                self::Refunding => 'Refunding',
                self::Refunded => 'Refunded',
                self::RefundFailed => 'Refund Failed',
                self::PartialCreditStopped => 'Order Partial Credit Stopped',
            },
        };
    }

    public function isCredited(): bool
    {
        return match ($this) {
            self::Success, self::Completed => true,
            default => false,
        };
    }

    public function isPending(): bool
    {
        return match ($this) {
            self::Processing, self::NeedAdditionalAction => true,
            default => false,
        };
    }

    /**
     * Nem pendente nem creditado: a ordem morreu sem virar saldo. É o mesmo
     * corte que decide se o brcode ainda sai na wire — um estado que não é
     * falha nunca esconde o brcode, e um que é nunca preenche `errorCode`.
     */
    public function isFailure(): bool
    {
        return !$this->isPending() && !$this->isCredited();
    }

    /**
     * O par (`errorCode`, `errorMessage`) que a doc lista no `data` de
     * get-order-detail e nunca define: `null` enquanto a ordem pode ainda ser
     * paga ou já foi. Os valores são vocabulário do fake — o consumidor não
     * decide por eles, decide por `status`; o que a venue real garante, e o fake
     * agora também, é que os dois campos EXISTEM na resposta.
     */
    public function errorCode(): ?string
    {
        return match ($this) {
            self::Processing, self::NeedAdditionalAction, self::Success, self::Completed => null,
            self::Failed => 'PAYMENT_FAILED',
            self::Expired => 'ORDER_EXPIRED',
            self::Cancelled => 'ORDER_CANCELLED',
            self::Refunding => 'REFUND_IN_PROGRESS',
            self::Refunded => 'PAYMENT_REFUNDED',
            self::RefundFailed => 'REFUND_FAILED',
            self::PartialCreditStopped => 'PARTIAL_CREDIT_STOPPED',
        };
    }

    public function errorMessage(): ?string
    {
        return match ($this) {
            self::Processing, self::NeedAdditionalAction, self::Success, self::Completed => null,
            self::Failed => 'the fiat payment failed and the order was closed',
            self::Expired => 'the order expired before the payment arrived',
            self::Cancelled => 'the order was cancelled before being credited',
            self::Refunding => 'the payment is being refunded to the sender',
            self::Refunded => 'the payment was refunded to the sender',
            self::RefundFailed => 'the refund attempt failed and the funds are held for review',
            self::PartialCreditStopped => 'only part of the payment arrived and crediting was stopped',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Processing => 'Processando',
            self::NeedAdditionalAction => 'Ação adicional necessária',
            self::Success => 'Creditado',
            self::Completed => 'Concluído (creditado)',
            self::Failed => 'Falhou',
            self::Expired => 'Expirado',
            self::Cancelled => 'Cancelado',
            self::Refunding => 'Estornando',
            self::Refunded => 'Estornado',
            self::RefundFailed => 'Falha no estorno',
            self::PartialCreditStopped => 'Crédito parcial interrompido',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Processing => 'gray',
            self::NeedAdditionalAction => 'warning',
            self::Success, self::Completed => 'success',
            self::Failed, self::Expired, self::Cancelled => 'danger',
            self::Refunding => 'warning',
            self::Refunded => 'gray',
            self::RefundFailed, self::PartialCreditStopped => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Processing => 'Ordem aberta, aguardando o avanço lazy pela idade',
            self::NeedAdditionalAction => 'A venue aguarda uma ação fora da API',
            self::Success => 'Ordem creditada no ledger (BRL)',
            self::Completed => 'Ordem creditada no ledger (BRL) — sinônimo de Success',
            self::Failed => 'Ordem morreu — nunca creditada',
            self::Expired => 'Ordem expirou antes de ser creditada',
            self::Cancelled => 'Ordem cancelada — nunca creditada',
            self::Refunding => 'Estorno em andamento',
            self::Refunded => 'Valor estornado ao remetente',
            self::RefundFailed => 'Estorno falhou — nunca creditada',
            self::PartialCreditStopped => 'Crédito parcial interrompido — nunca creditada',
        };
    }
}
