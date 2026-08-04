<?php

declare(strict_types=1);

namespace He4rt\Venue;

use He4rt\Venue\Ledger\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class VenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/venue-ledger.php', 'venue-ledger');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Relation::morphMap([
            'ledger_account' => LedgerAccount::class,
        ]);
    }
}
