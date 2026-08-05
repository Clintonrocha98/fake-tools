<?php

declare(strict_types=1);

namespace He4rt\Venue\Database\Seeders;

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Ledger\Models\LedgerAccount;
use He4rt\Venue\Ledger\Support\SeedBalancesParser;
use Illuminate\Database\Seeder;

final class LedgerAccountSeeder extends Seeder
{
    public function run(CreditLedgerAccount $credit): void
    {
        // Semear é criar o estado DE PARTIDA. Um ledger com qualquer conta já é
        // um ledger que evoluiu, e {@see CreditLedgerAccount} soma um delta em
        // vez de atribuir — recreditar sobre ele dobraria os saldos a cada
        // `db:seed`. Este guard é o que permite o entrypoint do container rodar
        // o seed em todo start sem precisar de marcador externo.
        if (LedgerAccount::query()->exists()) {
            return;
        }

        $balances = SeedBalancesParser::parse(config('venue-ledger.seed_balances'));

        foreach ($balances as $asset => $amount) {
            $credit->handle($asset, $amount);
        }
    }
}
