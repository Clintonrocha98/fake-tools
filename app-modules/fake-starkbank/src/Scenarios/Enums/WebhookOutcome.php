<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Enums;

use Filament\Support\Colors\Color;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;

/**
 * Os desfechos da PRÓXIMA emissão de evento, seja de qual perna for. Perna por
 * evento: o armado é consumido no instante da emissão
 * ({@see \He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent}), não na
 * criação do recurso de origem — "armar" vale para o próximo evento, não para
 * a próxima invoice.
 */
enum WebhookOutcome: string implements PixLegOutcomeContract
{
    case DuplicateNext = 'duplicate_next';
    case CorruptSignatureNext = 'corrupt_signature_next';
    case HoldNext = 'hold_next';

    public function leg(): PixLeg
    {
        return PixLeg::StarkbankWebhook;
    }

    /**
     * @return list<string>
     */
    public function payloadFields(): array
    {
        return match ($this) {
            self::DuplicateNext, self::CorruptSignatureNext, self::HoldNext => [],
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::DuplicateNext => 'Entregar o próximo evento duas vezes',
            self::CorruptSignatureNext => 'Assinar o próximo evento com outra chave',
            self::HoldNext => 'Represar o próximo evento',
        };
    }

    /**
     * @return string|array<int, string>
     */
    public function getColor(): string|array
    {
        return match ($this) {
            self::DuplicateNext => 'warning',
            self::CorruptSignatureNext => 'danger',
            self::HoldNext => Color::Indigo,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::DuplicateNext => 'O mesmo event.id chega duas vezes ao consumidor — exercita a idempotência por event_id do lado de lá',
            self::CorruptSignatureNext => 'A assinatura não fecha com o PEM público do consumidor; a resposta esperada é 401 e nada persistido',
            self::HoldNext => 'A emissão fica represada, sem POST, até um operador liberar em Emissões de webhook',
        };
    }
}
