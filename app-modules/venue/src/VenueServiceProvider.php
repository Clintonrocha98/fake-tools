<?php

declare(strict_types=1);

namespace He4rt\Venue;

use He4rt\Venue\Http\Middleware\VerifiesSignedRequest;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class VenueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/venue.php', 'venue');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('venue.signed', VerifiesSignedRequest::class);
    }
}
