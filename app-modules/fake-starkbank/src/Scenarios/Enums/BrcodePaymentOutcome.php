<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Enums;

use Filament\Support\Colors\Color;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;

/**
 * Os desfechos que o PRÓXIMO pagamento de BR Code pode receber, consumidos uma
 * única vez no `POST /v2/brcode-payment`. É o funding da conversão que fica em
 * jogo aqui: `Fail` devolve a `ConversionFundingSent` do consumidor e `Hold` a
 * deixa pendente sem desfecho.
 */
enum BrcodePaymentOutcome: string implements PixLegOutcomeContract
{
    case Fail = 'fail';
    case Hold = 'hold';

    public function leg(): PixLeg
    {
        return PixLeg::StarkbankBrcodePayment;
    }

    /**
     * @return list<string>
     */
    public function payloadFields(): array
    {
        return match ($this) {
            self::Fail => ['reason'],
            self::Hold => [],
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Fail => 'Nascer destinado a failed',
            self::Hold => 'Segurar em processing',
        };
    }

    /**
     * @return string|array<int, string>
     */
    public function getColor(): string|array
    {
        return match ($this) {
            self::Fail => 'danger',
            self::Hold => Color::Indigo,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Fail => 'O pagamento nasce destinado a failed, e o motivo digitado viaja no log do webhook seguinte',
            self::Hold => 'O pagamento congela em processing — nenhuma leitura o leva a success ou failed até ser desarmado',
        };
    }
}
