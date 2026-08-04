<?php

declare(strict_types=1);

namespace He4rt\Venue\Fiat\Models;

use App\Models\BaseModel;
use He4rt\Venue\Database\Factories\Fiat\FiatOrderFactory;
use He4rt\Venue\Fiat\Enums\FiatOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_no
 * @property string $currency
 * @property string $payment_method
 * @property numeric-string $amount
 * @property FiatOrderStatus $status
 * @property FiatOrderStatus|null $forced_status
 * @property string|null $forced_wire_status
 * @property string|null $brcode
 * @property bool $frozen
 * @property int|null $brcode_delay_reads
 * @property int $brcode_reads_count
 * @property Carbon|null $credited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<FiatOrderFactory>
 */
#[UseFactory(factoryClass: FiatOrderFactory::class)]
#[Table(name: 'venue_fiat_orders')]
final class FiatOrder extends BaseModel
{
    /**
     * O status que a wire deve responder agora: o override do painel
     * (`forced_status`) sempre vence o `status` computado pelo avanço lazy.
     * É uma máscara de leitura, não um congelamento — `forced_status` nunca é
     * gravado em `status` (ver {@see \He4rt\Venue\Fiat\Actions\GetFiatOrderDetail}),
     * então limpar o override deixa o avanço lazy retomar de onde estava.
     */
    public function effectiveStatus(): FiatOrderStatus
    {
        return $this->forced_status ?? $this->status;
    }

    /**
     * O brcode só sai na wire depois de `brcode_delay_reads` releituras — antes
     * disso, mesmo com `brcode` já persistido, a leitura enxerga `null`. `null`
     * em `brcode_delay_reads` preserva o comportamento default (brcode imediato).
     */
    public function brcodeVisible(): bool
    {
        return $this->brcode_delay_reads === null || $this->brcode_reads_count > $this->brcode_delay_reads;
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'status' => FiatOrderStatus::class,
            'forced_status' => FiatOrderStatus::class,
            'frozen' => 'boolean',
            'credited_at' => 'datetime',
        ];
    }
}
