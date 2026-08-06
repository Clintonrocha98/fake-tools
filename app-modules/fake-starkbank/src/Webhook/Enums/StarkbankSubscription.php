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
 * As três subscriptions que este fake emite — as pernas do fluxo forex. A
 * subscription `deposit` do StarkBank real fica de fora: o cash-in daqui é
 * sempre por invoice emitida.
 *
 * A assimetria de `logKey()` é do wire, não um descuido: a subscription é
 * `brcode-payment`, mas a entity dentro do log viaja em `payment` — é assim que
 * `WebhookEvent::fromWebhookBody()` do consumidor a lê.
 */
enum StarkbankSubscription: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Invoice = 'invoice';
    case Transfer = 'transfer';
    case BrcodePayment = 'brcode-payment';

    /**
     * A key sob a qual a entity completa entra em `event.log`.
     */
    public function logKey(): string
    {
        return match ($this) {
            self::Invoice => 'invoice',
            self::Transfer => 'transfer',
            self::BrcodePayment => 'payment',
        };
    }

    /**
     * Os `event.log.type` que esta subscription pode carregar. Emitir um tipo
     * fora daqui não quebra o consumidor (ele normaliza para `Unknown`), mas é
     * vocabulário que o StarkBank real nunca produz nessa perna.
     *
     * @return list<StarkbankEventType>
     */
    public function allowedEventTypes(): array
    {
        return match ($this) {
            self::Invoice => [
                StarkbankEventType::Created,
                StarkbankEventType::Credited,
                StarkbankEventType::Paid,
                StarkbankEventType::Overdue,
                StarkbankEventType::Expired,
                StarkbankEventType::Canceled,
                StarkbankEventType::Reversed,
            ],
            self::Transfer, self::BrcodePayment => [
                StarkbankEventType::Sending,
                StarkbankEventType::Processing,
                StarkbankEventType::Success,
                StarkbankEventType::Failed,
            ],
        };
    }

    public function allows(StarkbankEventType $eventType): bool
    {
        return in_array($eventType, $this->allowedEventTypes(), strict: true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice (cash-in)',
            self::Transfer => 'Transfer (cash-out)',
            self::BrcodePayment => 'BR Code payment (funding)',
        };
    }

    /**
     * Enum não-ordenado: as três pernas são caminhos distintos do dinheiro, não
     * uma escala — cada uma recebe cor própria, sem ramp.
     *
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Invoice => Color::Emerald,
            self::Transfer => Color::Sky,
            self::BrcodePayment => Color::Violet,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Invoice => 'Entrada de PIX contra uma invoice emitida pelo fake',
            self::Transfer => 'Saída de PIX para a conta do beneficiário',
            self::BrcodePayment => 'Pagamento de BR Code — o funding da venue; a entity viaja na key `payment`',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Invoice => Heroicon::OutlinedArrowDownTray,
            self::Transfer => Heroicon::OutlinedArrowUpTray,
            self::BrcodePayment => Heroicon::OutlinedQrCode,
        };
    }
}
