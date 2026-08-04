<?php

declare(strict_types=1);

namespace He4rt\Venue\Ledger\Models;

use App\Models\BaseModel;
use He4rt\Venue\Database\Factories\Ledger\LedgerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $asset
 * @property numeric-string $free
 * @property numeric-string $locked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @extends BaseModel<LedgerAccountFactory>
 */
#[UseFactory(factoryClass: LedgerAccountFactory::class)]
#[Table(name: 'venue_ledger_accounts')]
final class LedgerAccount extends BaseModel
{
    protected function casts(): array
    {
        return [
            'free' => 'decimal:18',
            'locked' => 'decimal:18',
        ];
    }
}
