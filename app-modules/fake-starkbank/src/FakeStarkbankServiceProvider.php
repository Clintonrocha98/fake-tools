<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank;

use He4rt\FakeStarkbank\Console\FlushWebhooksCommand;
use He4rt\FakeStarkbank\Http\Middleware\VerifiesSignedRequest;
use He4rt\FakeStarkbank\Webhook\Actions\EmitWebhookEvent;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class FakeStarkbankServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fake-starkbank.php', 'fake-starkbank');

        $this->app->bind(EmitsWebhookEvents::class, EmitWebhookEvent::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('fake-starkbank.signed', VerifiesSignedRequest::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushWebhooksCommand::class,
            ]);
        }

        Relation::morphMap([
            'starkbank_webhook_emission' => WebhookEmission::class,
        ]);
    }
}
