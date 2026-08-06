<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank;

use He4rt\FakeStarkbank\Http\Middleware\VerifiesSignedRequest;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class FakeStarkbankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fake-starkbank.php', 'fake-starkbank');

        $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('fake-starkbank.signed', VerifiesSignedRequest::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
