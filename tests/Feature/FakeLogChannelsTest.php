<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
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

        expect($monolog->getHandlers())->toHaveCount(1)
            ->and($monolog->getHandlers()[0])->toBeInstanceOf(TestHandler::class);
    }
});
