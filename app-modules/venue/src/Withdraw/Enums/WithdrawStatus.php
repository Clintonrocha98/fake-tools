<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * O vocabulário inteiro de status de withdraw da Binance real — os valores replicam
 * exatamente os códigos documentados (0 Email Sent … 6 Completed). Nunca inventar um
 * valor fora deste conjunto; um código desconhecido é falha fail-closed em quem lê.
 */
enum WithdrawStatus: int implements HasColor, HasDescription, HasIcon, HasLabel
{
    case EmailSent = 0;
    case Cancelled = 1;
    case AwaitingApproval = 2;
    case Rejected = 3;
    case Processing = 4;
    case Failure = 5;
    case Completed = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::EmailSent => 'E-mail enviado',
            self::Cancelled => 'Cancelado',
            self::AwaitingApproval => 'Aguardando aprovação',
            self::Rejected => 'Rejeitado',
            self::Processing => 'Processando',
            self::Failure => 'Falha',
            self::Completed => 'Concluído',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EmailSent => 'gray',
            self::Cancelled => 'gray',
            self::AwaitingApproval => 'warning',
            self::Rejected => 'danger',
            self::Processing => 'info',
            self::Failure => 'danger',
            self::Completed => 'success',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::EmailSent => 'Withdraw solicitado, aguardando confirmação por e-mail',
            self::Cancelled => 'Withdraw cancelado antes de ser processado',
            self::AwaitingApproval => 'Withdraw aceito, aguardando aprovação/avanço',
            self::Rejected => 'Withdraw rejeitado — motivo em `info`',
            self::Processing => 'Withdraw em processamento na rede',
            self::Failure => 'Withdraw falhou — motivo em `info`',
            self::Completed => 'Withdraw concluído — `txId` preenchido',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::EmailSent => Heroicon::OutlinedEnvelope,
            self::Cancelled => Heroicon::OutlinedXCircle,
            self::AwaitingApproval => Heroicon::OutlinedClock,
            self::Rejected => Heroicon::OutlinedXCircle,
            self::Processing => Heroicon::OutlinedArrowPath,
            self::Failure => Heroicon::OutlinedExclamationTriangle,
            self::Completed => Heroicon::OutlinedCheckCircle,
        };
    }

    /**
     * Estados em que o avanço automático (lazy, por idade) ainda pode agir — os
     * demais são terminais ou dependem de override manual do painel.
     */
    public function advancesAutomatically(): bool
    {
        return match ($this) {
            self::AwaitingApproval, self::Processing => true,
            self::EmailSent, self::Cancelled, self::Rejected, self::Failure, self::Completed => false,
        };
    }
}
