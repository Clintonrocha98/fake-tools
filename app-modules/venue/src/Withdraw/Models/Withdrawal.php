<?php

declare(strict_types=1);

namespace He4rt\Venue\Withdraw\Models;

use App\Models\BaseModel;
use He4rt\Venue\Database\Factories\Withdraw\WithdrawalFactory;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $coin
 * @property string $network
 * @property string $address
 * @property string|null $address_tag
 * @property numeric-string $amount
 * @property numeric-string $transaction_fee
 * @property string|null $withdraw_order_id
 * @property WithdrawStatus $status
 * @property string|null $tx_id
 * @property string|null $info
 * @property Carbon $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<WithdrawalFactory>
 */
#[UseFactory(factoryClass: WithdrawalFactory::class)]
#[Table(name: 'venue_withdrawals')]
final class Withdrawal extends BaseModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'transaction_fee' => 'decimal:18',
            'status' => WithdrawStatus::class,
            'applied_at' => 'datetime',
        ];
    }
}
