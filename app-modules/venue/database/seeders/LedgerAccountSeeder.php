<?php

declare(strict_types=1);

namespace He4rt\Venue\Database\Seeders;

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Ledger\Support\SeedBalancesParser;
use Illuminate\Database\Seeder;

final class LedgerAccountSeeder extends Seeder
{
    public function run(CreditLedgerAccount $credit): void
    {
        $balances = SeedBalancesParser::parse(config('venue-ledger.seed_balances'));

        foreach ($balances as $asset => $amount) {
            $credit($asset, $amount);
        }
    }
}
