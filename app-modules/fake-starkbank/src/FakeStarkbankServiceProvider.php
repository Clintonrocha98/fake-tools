<?php

declare(strict_types=1);

namespace He4rt\FakeStarkbank;

use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Console\FlushWebhooksCommand;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Http\Middleware\VerifiesSignedRequest;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Http\Middleware\ApplyPixScenarioSwitches;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
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
        $this->mergeConfigFrom(__DIR__.'/../config/fake-starkbank-invoice.php', 'fake-starkbank-invoice');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-starkbank-dict.php', 'fake-starkbank-dict');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-starkbank-transfer.php', 'fake-starkbank-transfer');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-starkbank-brcode.php', 'fake-starkbank-brcode');

        $this->app->bind(EmitsWebhookEvents::class, EmitWebhookEvent::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('fake-starkbank.signed', VerifiesSignedRequest::class);
        $router->aliasMiddleware('fake-starkbank.scenario-switches', ApplyPixScenarioSwitches::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushWebhooksCommand::class,
            ]);
        }

        Relation::morphMap([
            'starkbank_armed_scenario' => ArmedScenario::class,
            'starkbank_brcode_payment' => BrcodePayment::class,
            'starkbank_dict_entry' => DictEntry::class,
            'starkbank_invoice' => Invoice::class,
            'starkbank_scenario_switchboard' => ScenarioSwitchboard::class,
            'starkbank_transfer' => Transfer::class,
            'starkbank_webhook_emission' => WebhookEmission::class,
        ]);
    }
}
