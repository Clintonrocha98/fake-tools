<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Brcode\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;

/**
 * O vocabulário de status do pagamento de BR Code, exatamente como sai na
 * wire. É por esta string que o `BrcodePaymentSettlementMapper` do consumidor
 * decide o desfecho do funding da conversão — `success` sela a
 * `ConversionFundingSent`, `failed` a devolve —, então inventar um valor fora
 * deste conjunto é fazê-lo ignorar o desfecho.
 *
 * A perna não tem `returned`: um PIX de BR Code pago não volta sozinho no
 * vocabulário que o consumidor lê.
 */
enum BrcodePaymentStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Created = 'created';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';

    /**
     * Estados em que o avanço lazy (por idade, na leitura) ainda pode agir. Os
     * dois desfechos são terminais: nenhum relógio desfaz uma liquidação ou
     * uma recusa.
     */
    public function advancesAutomatically(): bool
    {
        return match ($this) {
            self::Created, self::Processing => true,
            self::Success, self::Failed => false,
        };
    }

    /**
     * O `event.log.type` que a entrada neste status emite — `null` quando a
     * transição é silenciosa.
     *
     * Só os dois desfechos que o consumidor classifica saem por webhook.
     * `created` e `processing` existem no ciclo de vida, mas emiti-los seria
     * gatilho para uma releitura que não muda nada do lado de lá.
     */
    public function eventType(): ?StarkbankEventType
    {
        return match ($this) {
            self::Success => StarkbankEventType::Success,
            self::Failed => StarkbankEventType::Failed,
            self::Created, self::Processing => null,
        };
    }

    /**
     * Se um `failure_reason` gravado descreve ESTE status. O `reason` do
     * envelope sai daquela coluna, então forçar um desfecho que não carrega
     * motivo tem de limpá-la: um `success` anunciado com o motivo da recusa
     * anterior descreve, dentro do envelope de liquidação, o oposto do que
     * aconteceu.
     */
    public function carriesFailureReason(): bool
    {
        return match ($this) {
            self::Failed => true,
            self::Created, self::Processing, self::Success => false,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Criado',
            self::Processing => 'Em processamento',
            self::Success => 'Liquidado',
            self::Failed => 'Recusado',
        };
    }

    /**
     * Enum não-ordenado: os quatro status são etapas e desfechos distintos de
     * um ciclo de vida, não uma escala de severidade — cada case tem cor
     * própria, sem ramp. As cores são as mesmas do `StarkbankEventType`
     * correspondente, para que a mesma transição não mude de cor entre a fila
     * de emissões e o pagamento.
     *
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Created => Color::Slate,
            self::Processing => Color::Indigo,
            self::Success => Color::Green,
            self::Failed => Color::Red,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Created => 'Pagamento aceito pelo provedor e ainda em fila',
            self::Processing => 'Em processamento na rede; nada foi liquidado ainda',
            self::Success => 'Liquidado — é este o funding que o consumidor concilia',
            self::Failed => 'Recusado pela rede; nada saiu da conta',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Created => Heroicon::OutlinedPlusCircle,
            self::Processing => Heroicon::OutlinedArrowPath,
            self::Success => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedXCircle,
        };
    }
}
