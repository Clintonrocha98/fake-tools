<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank\Scenarios\Enums;

use Filament\Support\Colors\Color;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;

/**
 * Os desfechos que a PRÓXIMA invoice emitida pode receber. Todos são
 * consumidos uma única vez, no `POST /v2/invoice`: o plano decide o destino da
 * linha e o avanço lazy das leituras seguintes só executa esse destino.
 *
 * A escala vai do desvio mais brando (paga, só que atrasada) ao mais duro (nem
 * paga nem expira, fica parada), e as cores acompanham.
 */
enum InvoiceOutcome: string implements PixLegOutcomeContract
{
    case Overdue = 'overdue';
    case Expire = 'expire';
    case Cancel = 'cancel';
    case FreezeStatus = 'freeze_status';
    case DelayPaid = 'delay_paid';

    public function leg(): PixLeg
    {
        return PixLeg::StarkbankInvoice;
    }

    /**
     * @return list<string>
     */
    public function payloadFields(): array
    {
        return match ($this) {
            self::Overdue, self::Expire, self::Cancel, self::FreezeStatus => [],
            self::DelayPaid => ['extraSeconds'],
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Overdue => 'Nascer vencida',
            self::Expire => 'Nascer expirada',
            self::Cancel => 'Nascer cancelada',
            self::FreezeStatus => 'Congelar o avanço',
            self::DelayPaid => 'Atrasar o pagamento',
        };
    }

    /**
     * @return string|array<int, string>
     */
    public function getColor(): string|array
    {
        return match ($this) {
            self::DelayPaid => 'info',
            self::FreezeStatus => 'gray',
            self::Overdue => Color::Amber,
            self::Expire => Color::Orange,
            self::Cancel => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Overdue => 'A cobrança nasce destinada a overdue — a próxima releitura já a entrega vencida',
            self::Expire => 'A cobrança nasce destinada a expired — o brcode nunca chega a valer',
            self::Cancel => 'A cobrança nasce destinada a canceled',
            self::FreezeStatus => 'A cobrança nasce congelada: nenhuma leitura move o status até um operador descongelar',
            self::DelayPaid => 'A cobrança paga normalmente, só que os segundos extras somam ao relógio do avanço lazy',
        };
    }
}
