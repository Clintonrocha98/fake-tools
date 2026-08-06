<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank;

use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;
use Illuminate\Support\ServiceProvider;

class FakeStarkbankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fake-starkbank.php', 'fake-starkbank');

        $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
