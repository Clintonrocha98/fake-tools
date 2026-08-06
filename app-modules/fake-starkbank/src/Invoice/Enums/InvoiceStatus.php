<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Invoice\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;

/**
 * O vocabulário de status de invoice do StarkBank, exatamente como sai na wire.
 * O consumidor classifica o Settlement por esta string
 * (`InvoiceSettlementMapper`), então inventar um valor fora deste conjunto é
 * fazê-lo ignorar o desfecho.
 *
 * `credited` e `reversed` existem no vocabulário mas nenhuma transição do fake
 * os produz: só chegam por cenário forçado, para exercitar o ramo do lado de lá.
 */
enum InvoiceStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Created = 'created';
    case Credited = 'credited';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Expired = 'expired';
    case Canceled = 'canceled';
    case Reversed = 'reversed';

    /**
     * Estados em que o avanço lazy (por idade, na leitura) ainda pode agir. Os
     * demais são terminais ou dependem de cenário — `credited` inclusive: é um
     * estado que só um cenário arma, e deixá-lo avançar sozinho apagaria
     * justamente o ramo que se queria observar.
     */
    public function advancesAutomatically(): bool
    {
        return match ($this) {
            self::Created, self::Overdue => true,
            self::Credited, self::Paid, self::Expired, self::Canceled, self::Reversed => false,
        };
    }

    /**
     * O `event.log.type` que a entrada neste status emite. Um a um: o
     * vocabulário de status e o de evento do StarkBank coincidem na perna de
     * invoice.
     */
    public function eventType(): StarkbankEventType
    {
        return match ($this) {
            self::Created => StarkbankEventType::Created,
            self::Credited => StarkbankEventType::Credited,
            self::Paid => StarkbankEventType::Paid,
            self::Overdue => StarkbankEventType::Overdue,
            self::Expired => StarkbankEventType::Expired,
            self::Canceled => StarkbankEventType::Canceled,
            self::Reversed => StarkbankEventType::Reversed,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Emitida',
            self::Credited => 'Creditada',
            self::Paid => 'Paga',
            self::Overdue => 'Vencida (em graça)',
            self::Expired => 'Expirada',
            self::Canceled => 'Cancelada',
            self::Reversed => 'Estornada',
        };
    }

    /**
     * Enum não-ordenado: os sete status são desfechos distintos de um ciclo de
     * vida, não uma escala de severidade — cada case tem cor própria, sem ramp.
     * As cores são as mesmas do `StarkbankEventType` correspondente, para que a
     * mesma transição não mude de cor entre a fila de emissões e a invoice.
     *
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Created => Color::Slate,
            self::Credited => Color::Teal,
            self::Paid => Color::Emerald,
            self::Overdue => Color::Amber,
            self::Expired => Color::Orange,
            self::Canceled => Color::Stone,
            self::Reversed => Color::Fuchsia,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Created => 'Emitida e aguardando pagamento — o brcode já vale',
            self::Credited => 'Crédito parcial reconhecido; o fake só chega aqui por cenário',
            self::Paid => 'Paga — é este o cash-in que o consumidor concilia',
            self::Overdue => 'Passou do vencimento e ainda aceita pagamento dentro da graça',
            self::Expired => 'Graça esgotada sem pagamento; o brcode não vale mais',
            self::Canceled => 'Cancelada antes do pagamento',
            self::Reversed => 'Pagamento devolvido ao pagador; o fake só chega aqui por cenário',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Created => Heroicon::OutlinedPlusCircle,
            self::Credited => Heroicon::OutlinedBanknotes,
            self::Paid => Heroicon::OutlinedCheckCircle,
            self::Overdue => Heroicon::OutlinedClock,
            self::Expired => Heroicon::OutlinedExclamationTriangle,
            self::Canceled => Heroicon::OutlinedNoSymbol,
            self::Reversed => Heroicon::OutlinedArrowUturnLeft,
        };
    }
}
