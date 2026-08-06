<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Webhook\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * O `event.log.type` que o fake sabe emitir. Gêmeo declarado do
 * `StarkbankEventType` do consumidor, menos o case `Unknown`: lá `Unknown` é o
 * fallback de leitura para um vocabulário novo do StarkBank; aqui seria um tipo
 * que o fake decidiu inventar, e emitir vocabulário inexistente não é fidelidade.
 *
 * Que tipos valem em cada perna é decisão da subscription
 * ({@see StarkbankSubscription::allowedEventTypes()}), não deste enum: os quatro
 * tipos de cash-out são compartilhados por `transfer` e `brcode-payment`.
 */
enum StarkbankEventType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    // Ciclo de vida da invoice (cash-in).
    case Created = 'created';
    case Credited = 'credited';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Expired = 'expired';
    case Canceled = 'canceled';
    case Reversed = 'reversed';

    // Ciclo de vida de transfer / brcode-payment (cash-out).
    case Sending = 'sending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';

    /**
     * Desfecho terminal: nenhum outro evento sai depois deste para a mesma
     * entity. É o que o consumidor relê por GET e concilia.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Paid, self::Expired, self::Canceled, self::Reversed,
            self::Success, self::Failed => true,
            self::Created, self::Credited, self::Overdue,
            self::Sending, self::Processing => false,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Criada',
            self::Credited => 'Creditada',
            self::Paid => 'Paga',
            self::Overdue => 'Vencida',
            self::Expired => 'Expirada',
            self::Canceled => 'Cancelada',
            self::Reversed => 'Estornada',
            self::Sending => 'Enviando',
            self::Processing => 'Processando',
            self::Success => 'Concluída',
            self::Failed => 'Falhou',
        };
    }

    /**
     * Enum não-ordenado: os onze tipos pertencem a dois ciclos de vida
     * paralelos (cash-in e cash-out) que nunca se comparam entre si, então não
     * há escala a rampar. Cada case recebe uma cor própria — os desfechos
     * adversos ficam no vermelho da paleta, os de progresso no azul.
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
            self::Sending => Color::Sky,
            self::Processing => Color::Indigo,
            self::Success => Color::Green,
            self::Failed => Color::Red,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Created => 'Invoice emitida e ainda sem pagamento',
            self::Credited => 'Valor da invoice creditado na conta do workspace',
            self::Paid => 'Invoice paga pelo pagador — o cash-in que o consumidor concilia',
            self::Overdue => 'Invoice passou do vencimento e ainda aceita pagamento',
            self::Expired => 'Invoice passou da janela de pagamento e não aceita mais',
            self::Canceled => 'Invoice cancelada antes do pagamento',
            self::Reversed => 'Pagamento da invoice devolvido ao pagador',
            self::Sending => 'Saída aceita e a caminho da rede',
            self::Processing => 'Saída em processamento na rede',
            self::Success => 'Saída liquidada — o cash-out que o consumidor concilia',
            self::Failed => 'Saída recusada pela rede; nada saiu da conta',
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
            self::Sending => Heroicon::OutlinedPaperAirplane,
            self::Processing => Heroicon::OutlinedArrowPath,
            self::Success => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedXCircle,
        };
    }
}
