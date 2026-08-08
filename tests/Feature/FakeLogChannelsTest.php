<?php

declare(strict_types=1);

use He4rt\Control\Feed\ControlEventHandler;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;

/*
|--------------------------------------------------------------------------
| Canais de log dedicados dos fakes
|--------------------------------------------------------------------------
|
| BinanceLog e StarkbankLog resolvem os canais `binance` e `starkbank` pelo
| nome; um canal removido de config/logging.php não estoura — o LogManager cai
| no emergency logger e as linhas somem em silêncio. O contrato é lido direto
| do arquivo porque o TestCase base troca a config em runtime pelo TestHandler
| em memória — que o último teste garante, para a suíte nunca voltar a poluir
| os arquivos que o log-viewer serve.
|
*/

test('os canais binance e starkbank escrevem em arquivos diários próprios', function (): void {
    /** @var array{channels: array<string, array{driver: string, path?: string}>} $logging */
    $logging = require base_path('config/logging.php');

    expect($logging['channels']['binance']['driver'])->toBe('daily')
        ->and($logging['channels']['binance']['path'])->toEndWith('logs/binance.log')
        ->and($logging['channels']['starkbank']['driver'])->toBe('daily')
        ->and($logging['channels']['starkbank']['path'])->toEndWith('logs/starkbank.log');
});

test('durante a suíte os canais dos fakes são capturados em memória, nunca em disco', function (): void {
    foreach (['binance', 'starkbank'] as $channel) {
        $monolog = Log::channel($channel)->getLogger();
        assert($monolog instanceof Logger);

        $handlers = $monolog->getHandlers();

        // Além do TestHandler, o tap do plano de controle empurra o produtor do
        // feed para o mesmo canal — ele grava em `control_events`, também fora
        // do disco. O que este teste guarda é a ausência de handler de ARQUIVO.
        $inesperados = array_filter(
            $handlers,
            static fn (HandlerInterface $handler): bool => !$handler instanceof TestHandler
                && !$handler instanceof ControlEventHandler,
        );

        expect($inesperados)->toBeEmpty()
            ->and(array_filter($handlers, static fn (HandlerInterface $handler): bool => $handler instanceof TestHandler))
            ->toHaveCount(1)
            ->and(array_filter($handlers, static fn (HandlerInterface $handler): bool => $handler instanceof StreamHandler))
            ->toBeEmpty();
    }
});
