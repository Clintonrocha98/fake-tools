<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Scenarios\Enums;

use Filament\Support\Colors\Color;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;

/**
 * Os desfechos que uma conversão spot pode receber quando armada. A escala vai
 * do desvio mais brando (a ordem executa, só que pela metade) ao mais duro (a
 * venue nem processa), e as cores acompanham.
 *
 * `RespondRejected` é o único sem paralelo confirmado na venue real: a doc da
 * Binance descreve REJECTED como "not accepted by the engine and not
 * processed", o que na colocação chega como envelope de erro, não como 200. Fica
 * no catálogo porque o mapeamento existe do lado do consumidor.
 */
enum SpotConversionOutcome: string implements LegOutcomeContract
{
    case FillPartialExpired = 'fill_partial_expired';
    case EmitUnknownStatus = 'emit_unknown_status';
    case RespondRejected = 'respond_rejected';
    case RefuseWithCode = 'refuse_with_code';

    public function leg(): VenueLeg
    {
        return VenueLeg::SpotConversion;
    }

    /**
     * @return list<string>
     */
    public function payloadFields(): array
    {
        return match ($this) {
            self::FillPartialExpired => ['fraction'],
            self::EmitUnknownStatus => ['rawStatus'],
            self::RespondRejected => [],
            self::RefuseWithCode => ['errorCode'],
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::FillPartialExpired => 'Preencher parcial e expirar o resto',
            self::EmitUnknownStatus => 'Emitir vocabulário desconhecido',
            self::RespondRejected => 'Responder REJECTED',
            self::RefuseWithCode => 'Recusar com código',
        };
    }

    public function getColor(): string|array
    {
        return match ($this) {
            self::FillPartialExpired => 'warning',
            self::EmitUnknownStatus => Color::Orange,
            self::RespondRejected => Color::Rose,
            self::RefuseWithCode => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::FillPartialExpired => 'A ordem executa só a fração armada e responde EXPIRED — o ledger recebe apenas essa fração',
            self::EmitUnknownStatus => 'A ordem executa normal, mas a wire responde um status fora do vocabulário',
            self::RespondRejected => 'HTTP 200 com status REJECTED e nenhum fill — o ledger não é tocado',
            self::RefuseWithCode => 'Envelope de erro da família /api/v3 — nenhuma ordem é criada',
        };
    }
}
