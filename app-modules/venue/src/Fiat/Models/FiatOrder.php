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
 * @property string|null $brcode
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
     * (`forced_status`, #7) sempre vence o `status` computado pelo avanço
     * lazy — é o que torna a coluna um "congelamento" de fato.
     */
    public function effectiveStatus(): FiatOrderStatus
    {
        return $this->forced_status ?? $this->status;
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'status' => FiatOrderStatus::class,
            'forced_status' => FiatOrderStatus::class,
            'credited_at' => 'datetime',
        ];
    }
}
