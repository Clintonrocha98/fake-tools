<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * O vocabulário de status de depósito cripto da Binance real — os valores
 * replicam exatamente os códigos documentados de
 * GET /sapi/v1/capital/deposit/hisrec (0 pending, 1 success, 6 credited but
 * cannot withdraw, 7 wrong deposit, 8 waiting user confirm). Nunca inventar um
 * valor fora deste conjunto; um código desconhecido é falha fail-closed em
 * quem lê.
 */
enum DepositStatus: int implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Pending = 0;
    case Success = 1;
    case Credited = 6;
    case WrongDeposit = 7;
    case WaitingUserConfirm = 8;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Success => 'Sucesso',
            self::Credited => 'Creditado (sem saque)',
            self::WrongDeposit => 'Depósito errado',
            self::WaitingUserConfirm => 'Aguardando confirmação do usuário',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Success => 'success',
            self::Credited => 'info',
            self::WrongDeposit => 'danger',
            self::WaitingUserConfirm => 'warning',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Pending => 'Transferência vista on-chain, ainda sem crédito no saldo',
            self::Success => 'Depósito concluído — saldo livre para qualquer uso',
            self::Credited => 'Saldo creditado e negociável, saque ainda bloqueado',
            self::WrongDeposit => 'Depósito inválido (rede/memo errados) — nunca credita',
            self::WaitingUserConfirm => 'Aguardando ação manual do usuário — nunca avança sozinho',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pending => Heroicon::OutlinedClock,
            self::Success => Heroicon::OutlinedCheckCircle,
            self::Credited => Heroicon::OutlinedBanknotes,
            self::WrongDeposit => Heroicon::OutlinedXCircle,
            self::WaitingUserConfirm => Heroicon::OutlinedHandRaised,
        };
    }

    /**
     * Estados em que o avanço automático (lazy, por idade) ainda pode agir —
     * os demais são terminais ou dependem de ação manual.
     */
    public function advancesAutomatically(): bool
    {
        return match ($this) {
            self::Pending, self::Credited => true,
            self::Success, self::WrongDeposit, self::WaitingUserConfirm => false,
        };
    }
}
