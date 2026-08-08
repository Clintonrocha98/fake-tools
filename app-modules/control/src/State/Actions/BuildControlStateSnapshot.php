<?php

declare(strict_types=1);

namespace He4rt\Control\State\Actions;

use He4rt\Control\State\DTOs\ControlStateSnapshot;
use Illuminate\Support\Facades\Date;

/**
 * O retrato dos dois fakes numa request. Zero lógica nova de negócio: só
 * consulta e serializa.
 */
final readonly class BuildControlStateSnapshot
{
    public function __construct(
        private BuildStarkbankStateSnapshot $starkbank = new BuildStarkbankStateSnapshot,
        private BuildBinanceStateSnapshot $binance = new BuildBinanceStateSnapshot,
    ) {}

    public function handle(?int $limite = null): ControlStateSnapshot
    {
        $limite ??= (int) config('control.state.recent_limit', 10);

        return new ControlStateSnapshot(
            starkbank: $this->starkbank->handle($limite),
            binance: $this->binance->handle($limite),
            takenAt: Date::now()->toIso8601String(),
        );
    }
}
