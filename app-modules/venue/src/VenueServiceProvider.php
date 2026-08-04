<?php

declare(strict_types=1);

namespace He4rt\Venue;

use He4rt\Venue\Fiat\Models\FiatOrder;
use He4rt\Venue\Http\Middleware\VerifiesSignedRequest;
use He4rt\Venue\Ledger\Models\LedgerAccount;
use He4rt\Venue\Spot\Models\SpotOrder;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class VenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/venue.php', 'venue');
        $this->mergeConfigFrom(__DIR__.'/../config/venue-ledger.php', 'venue-ledger');
        $this->mergeConfigFrom(__DIR__.'/../config/venue-fiat.php', 'venue-fiat');
        $this->mergeConfigFrom(__DIR__.'/../config/venue-spot.php', 'venue-spot');
        $this->mergeConfigFrom(__DIR__.'/../config/venue-withdraw.php', 'venue-withdraw');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('venue.signed', VerifiesSignedRequest::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Relation::morphMap([
            'ledger_account' => LedgerAccount::class,
            'fiat_order' => FiatOrder::class,
            'spot_order' => SpotOrder::class,
            'withdrawal' => Withdrawal::class,
        ]);
    }
}
