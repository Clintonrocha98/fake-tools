<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Fiat\Models;

use App\Models\BaseModel;
use He4rt\FakeBinance\Database\Factories\Fiat\FiatWithdrawalFactory;
use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * Um saque em reais via POST /sapi/v2/fiat/withdraw (PIX → conta bancária) —
 * tabela própria, nunca uma FiatOrder: a ordem fiat de ENTRADA carrega
 * brcode/máscaras de leitura que não existem na saída, e o
 * get-order-detail só resolve ordens de entrada. O BRL é debitado no aceite
 * (ADR-0003); a idempotência é por `client_order_id`.
 *
 * @property string $id
 * @property string $order_id
 * @property string $currency
 * @property string $payment_method
 * @property numeric-string $amount
 * @property string $account_number
 * @property string|null $agency
 * @property string|null $bank_code_for_pix
 * @property string|null $account_type
 * @property string $client_order_id
 * @property FiatOrderStatus $status
 * @property Carbon $requested_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<FiatWithdrawalFactory>
 */
#[UseFactory(factoryClass: FiatWithdrawalFactory::class)]
#[Table(name: 'fake_binance_fiat_withdrawals')]
final class FiatWithdrawal extends BaseModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'status' => FiatOrderStatus::class,
            'requested_at' => 'datetime',
        ];
    }
}
