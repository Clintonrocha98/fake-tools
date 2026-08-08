<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\PermissionsSeeder;
use He4rt\Control\Feed\PersistsToControlFeed;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Monolog\Handler\TestHandler;
use Tests\Traits\CreatesApplication;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Seeded once per process by RefreshDatabase (forwarded to migrate:fresh
     * --seeder), committed before the per-test transaction — so the RBAC
     * baseline is visible to every test without a per-test rebuild.
     *
     * @var class-string<Seeder>
     */
    protected string $seeder = PermissionsSeeder::class;

    /**
     * Os canais dos fakes escrevem em storage/logs/ — os mesmos arquivos que o
     * log-viewer serve ao dev, que a suíte poluiria com milhares de linhas. O
     * LOG_CHANNEL=null do .env.testing só silencia o canal default; canais
     * nomeados passam por fora dele, então cada um vira aqui um TestHandler em
     * memória: nada toca o disco e todo teste pode assertar o que foi logado
     * via fakeLogRecords() (tests/Pest.php).
     *
     * O `tap` do plano de controle acompanha a troca — o produtor do feed é o
     * mesmo caminho em teste e em dev, e `fakeLogRecords()` continua lendo o
     * TestHandler porque ele segue sendo o handler de índice 0.
     *
     * A conexão do feed aponta para a default aqui: uma segunda conexão não
     * enxergaria a transação do teste, e o rollback do teste não desfaria o que
     * ela escreveu.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('control.connection', config('database.default'));

        foreach (['binance', 'starkbank'] as $channel) {
            config()->set('logging.channels.'.$channel, [
                'driver' => 'monolog',
                'handler' => TestHandler::class,
                'tap' => [PersistsToControlFeed::class.':'.$channel],
            ]);
        }
    }
}
