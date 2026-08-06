<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Models;

use App\Models\BaseModel;
use He4rt\FakeBinance\Database\Factories\Spot\SpotOrderFactory;
use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * Uma ordem Spot MARKET no par USDCBRL — sempre um único fill (o fake não
 * modela um order book real, preenche tudo de uma vez ao preço do
 * bookTicker), por isso o preço/comissão do fill vivem como colunas
 * escalares, nunca uma lista de fills em JSON.
 *
 * O status nasce do {@see \He4rt\FakeBinance\Spot\DTOs\SpotExecutionPlan} que
 * {@see \He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder} executa: FILLED no
 * plano neutro, e parcial+EXPIRED, REJECTED zerado ou `raw_status_override`
 * quando há cenário armado. As ações pós-fato do painel continuam rasurando o
 * registro depois de emitido — a venue real também muda de status entre o POST
 * e o GET.
 *
 * @property string $id
 * @property int $order_id
 * @property string $client_order_id
 * @property string $symbol
 * @property OrderSide $side
 * @property string $type
 * @property OrderStatus $status
 * @property numeric-string|null $quantity
 * @property numeric-string|null $quote_order_qty
 * @property numeric-string $executed_qty
 * @property numeric-string $cummulative_quote_qty
 * @property numeric-string|null $fill_price
 * @property numeric-string $commission
 * @property string|null $commission_asset
 * @property string|null $raw_status_override
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<SpotOrderFactory>
 */
#[UseFactory(factoryClass: SpotOrderFactory::class)]
#[Table(name: 'fake_binance_spot_orders')]
final class SpotOrder extends BaseModel
{
    /**
     * Nome deliberadamente diferente de `fills` — um método público `fills()`
     * num Model Eloquent é lido por `isRelation()` (`method_exists()`) como
     * uma relação e explode em `LogicException` no primeiro acesso a
     * `$order->fills`.
     *
     * @return list<array{price: string, qty: string, commission: string, commissionAsset: string}>
     */
    public function wireFills(): array
    {
        if ($this->fill_price === null || bccomp($this->executed_qty, '0', 18) <= 0) {
            return [];
        }

        return [[
            'price' => LedgerAmount::wire((string) $this->fill_price),
            'qty' => LedgerAmount::wire((string) $this->executed_qty),
            'commission' => LedgerAmount::wire((string) $this->commission),
            'commissionAsset' => (string) $this->commission_asset,
        ]];
    }

    protected function casts(): array
    {
        return [
            'side' => OrderSide::class,
            'status' => OrderStatus::class,
            'quantity' => 'decimal:18',
            'quote_order_qty' => 'decimal:18',
            'executed_qty' => 'decimal:18',
            'cummulative_quote_qty' => 'decimal:18',
            'fill_price' => 'decimal:18',
            'commission' => 'decimal:18',
        ];
    }
}
