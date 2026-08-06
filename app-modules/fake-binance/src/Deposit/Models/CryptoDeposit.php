<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Deposit\Models;

use App\Models\BaseModel;
use He4rt\FakeBinance\Database\Factories\Deposit\CryptoDepositFactory;
use He4rt\FakeBinance\Deposit\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * Uma chegada de stablecoin vinda de fora (a Dakota transferiu) — a única
 * perna cujo dinheiro NÃO nasce de um pedido do consumidor. É semeada por
 * {@see \He4rt\FakeBinance\Deposit\Actions\AnnounceCryptoDeposit} (comando
 * artisan `fake-binance:announce-deposit` ou painel) e depois só avança por
 * idade, lazy, quando o hisrec é lido (ADR-0003).
 *
 * @property string $id
 * @property string $coin
 * @property string $network
 * @property string $address
 * @property string|null $address_tag
 * @property numeric-string $amount
 * @property string $tx_id
 * @property DepositStatus $status
 * @property Carbon $announced_at
 * @property Carbon|null $credited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<CryptoDepositFactory>
 */
#[UseFactory(factoryClass: CryptoDepositFactory::class)]
#[Table(name: 'fake_binance_crypto_deposits')]
final class CryptoDeposit extends BaseModel
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'status' => DepositStatus::class,
            'announced_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }
}
