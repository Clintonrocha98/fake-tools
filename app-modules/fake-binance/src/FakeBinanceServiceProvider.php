<?php

declare(strict_types=1);

namespace He4rt\FakeBinance;

use He4rt\FakeBinance\Console\AnnounceCryptoDepositCommand;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Fiat\Models\FiatWithdrawal;
use He4rt\FakeBinance\Http\Middleware\LogsRequestLifecycle;
use He4rt\FakeBinance\Http\Middleware\VerifiesSignedRequest;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Scenarios\Http\Middleware\ApplyScenarioSwitches;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class FakeBinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fake-binance.php', 'fake-binance');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-binance-ledger.php', 'fake-binance-ledger');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-binance-fiat.php', 'fake-binance-fiat');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-binance-spot.php', 'fake-binance-spot');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-binance-withdraw.php', 'fake-binance-withdraw');
        $this->mergeConfigFrom(__DIR__.'/../config/fake-binance-deposit.php', 'fake-binance-deposit');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('fake-binance.request-log', LogsRequestLifecycle::class);
        $router->aliasMiddleware('fake-binance.signed', VerifiesSignedRequest::class);
        $router->aliasMiddleware('fake-binance.scenario-switches', ApplyScenarioSwitches::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AnnounceCryptoDepositCommand::class,
            ]);
        }

        Relation::morphMap([
            'ledger_account' => LedgerAccount::class,
            'fiat_order' => FiatOrder::class,
            'fiat_withdrawal' => FiatWithdrawal::class,
            'spot_order' => SpotOrder::class,
            'withdrawal' => Withdrawal::class,
            'crypto_deposit' => CryptoDeposit::class,
            'scenario_switchboard' => ScenarioSwitchboard::class,
            'armed_scenario' => ArmedScenario::class,
        ]);
    }
}
