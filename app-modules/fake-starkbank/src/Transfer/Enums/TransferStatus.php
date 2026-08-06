<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Transfer\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use He4rt\FakeStarkbank\Webhook\Enums\StarkbankEventType;

/**
 * O vocabulário de status de transfer do StarkBank, exatamente como sai na
 * wire. É por esta string que `TransferSettlementMapper` do consumidor decide o
 * `SettlementKind` do cash-out — `success` sela o Payout, `failed`/`returned` o
 * devolvem —, então inventar um valor fora deste conjunto é fazê-lo ignorar o
 * desfecho.
 *
 * `returned` existe no vocabulário mas nenhuma transição do fake o produz: é a
 * devolução depois de o dinheiro já ter saído, e só um cenário forçado chega lá.
 */
enum TransferStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Created = 'created';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Returned = 'returned';

    /**
     * Estados em que o avanço lazy (por idade, na leitura) ainda pode agir. Os
     * três desfechos são terminais: nenhum relógio desfaz uma liquidação, uma
     * recusa ou uma devolução.
     */
    public function advancesAutomatically(): bool
    {
        return match ($this) {
            self::Created, self::Processing => true,
            self::Success, self::Failed, self::Returned => false,
        };
    }

    /**
     * O `event.log.type` que a entrada neste status emite — `null` quando a
     * transição é silenciosa.
     *
     * Só os dois desfechos que o `TransferSettlementMapper` do consumidor
     * classifica saem por webhook. `created` e `processing` existem no ciclo de
     * vida, mas emiti-los seria gatilho para uma releitura que não muda nada do
     * lado de lá; `returned` é produzido só por cenário, e o cenário emite o
     * evento que quer observar.
     */
    public function eventType(): ?StarkbankEventType
    {
        return match ($this) {
            self::Success => StarkbankEventType::Success,
            self::Failed => StarkbankEventType::Failed,
            self::Created, self::Processing, self::Returned => null,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Created => 'Criada',
            self::Processing => 'Em processamento',
            self::Success => 'Liquidada',
            self::Failed => 'Recusada',
            self::Returned => 'Devolvida',
        };
    }

    /**
     * Enum não-ordenado: os cinco status são etapas e desfechos distintos de um
     * ciclo de vida, não uma escala de severidade — cada case tem cor própria,
     * sem ramp. As cores são as mesmas do `StarkbankEventType` correspondente,
     * para que a mesma transição não mude de cor entre a fila de emissões e a
     * transfer.
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
            self::Returned => Color::Fuchsia,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Created => 'Transfer aceita pelo provedor e ainda não despachada',
            self::Processing => 'Em trânsito na rede; nada foi liquidado ainda',
            self::Success => 'Liquidada — é este o cash-out que o consumidor concilia',
            self::Failed => 'Recusada pela rede; nada saiu da conta',
            self::Returned => 'Devolvida depois de ter saído; o fake só chega aqui por cenário',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Created => Heroicon::OutlinedPlusCircle,
            self::Processing => Heroicon::OutlinedArrowPath,
            self::Success => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedXCircle,
            self::Returned => Heroicon::OutlinedArrowUturnLeft,
        };
    }
}
