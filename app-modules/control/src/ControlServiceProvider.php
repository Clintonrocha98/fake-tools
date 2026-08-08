<?php

declare(strict_types=1);

namespace He4rt\Control;

use He4rt\Control\Feed\Models\ControlEvent;
use He4rt\Control\Http\TranslatesDomainRefusals;
use He4rt\Control\Reset\Console\ResetBaselineCommand;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class ControlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/control.php', 'control');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->callAfterResolving(ExceptionHandler::class, static function (ExceptionHandler $handler): void {
            TranslatesDomainRefusals::register($handler);
        });

        // O comando continua disponível com o kill-switch desligado — é
        // justamente quando o HTTP não está.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ResetBaselineCommand::class,
            ]);
        }

        Relation::morphMap([
            'control_event' => ControlEvent::class,
        ]);
    }
}
