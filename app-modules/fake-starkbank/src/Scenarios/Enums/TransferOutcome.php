<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Enums;

use Filament\Support\Colors\Color;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;

/**
 * Os desfechos que a PRÓXIMA transfer despachada pode receber, consumidos uma
 * única vez no `POST /v2/transfer`.
 *
 * `Hold` é o desvio brando (o cash-out simplesmente não conclui) e `Fail` é o
 * duro (a rede recusa e o consumidor devolve o Payout).
 */
enum TransferOutcome: string implements PixLegOutcomeContract
{
    case Fail = 'fail';
    case Hold = 'hold';

    public function leg(): PixLeg
    {
        return PixLeg::StarkbankTransfer;
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
            self::Fail => 'Nascer destinada a failed',
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
            self::Fail => 'A transfer nasce destinada a failed, e o motivo digitado viaja no log do webhook seguinte',
            self::Hold => 'A transfer congela em processing — nenhuma leitura a leva a success ou failed até ser desarmada',
        };
    }
}
