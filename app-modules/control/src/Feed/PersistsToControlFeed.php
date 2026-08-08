<?php

declare(strict_types=1);

namespace He4rt\Control\Feed;

use Illuminate\Log\Logger;
use Monolog\Logger as Monolog;

/**
 * O encaixe do produtor do feed nos canais `binance`/`starkbank`. Eles são
 * `driver => daily` puro, não stacks, então não há ponto de composição na
 * config — um `tap` empurra o handler extra para o mesmo canal, o arquivo
 * diário continua idêntico e `BinanceLog`/`StarkbankLog` seguem chamando
 * `Log::channel(...)` sem saber que existe um plano de controle.
 *
 * Virar os canais em stack faria o mesmo renomeando o canal-arquivo e
 * obrigando a revisar todo mundo que escreve nele — raio maior sem ganho.
 *
 * O canal vem por argumento do tap (`PersistsToControlFeed::class.':binance'`)
 * e nunca de `Monolog::getName()`: o `LogManager` nomeia o Monolog com o
 * ambiente da app, não com o canal, então inferir daria `local` nas duas
 * colunas e o feed perderia a distinção entre as duas malhas.
 */
final readonly class PersistsToControlFeed
{
    public function __invoke(Logger $logger, string $channel = ''): void
    {
        if ($channel === '') {
            return;
        }

        $monolog = $logger->getLogger();

        if (!$monolog instanceof Monolog) {
            return;
        }

        $monolog->pushHandler(new ControlEventHandler($channel));
    }
}
