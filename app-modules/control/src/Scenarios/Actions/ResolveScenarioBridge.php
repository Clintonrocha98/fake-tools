<?php

declare(strict_types=1);

namespace He4rt\Control\Scenarios\Actions;

use He4rt\Control\Http\Exceptions\UnknownFakeException;
use He4rt\Control\Scenarios\Bridges\BinanceScenarioBridge;
use He4rt\Control\Scenarios\Bridges\StarkbankScenarioBridge;
use He4rt\Control\Scenarios\Contracts\ScenarioBridgeContract;

/**
 * O segmento `{fake}` da rota → a bridge do fake. É aqui, e só aqui, que o
 * plano de controle escolhe entre os dois conjuntos de Actions gêmeas.
 */
final readonly class ResolveScenarioBridge
{
    /**
     * @return list<string>
     */
    public static function fakes(): array
    {
        return ['starkbank', 'binance'];
    }

    public function handle(string $fake): ScenarioBridgeContract
    {
        return match ($fake) {
            'starkbank' => new StarkbankScenarioBridge,
            'binance' => new BinanceScenarioBridge,
            default => throw UnknownFakeException::for($fake, self::fakes()),
        };
    }
}
