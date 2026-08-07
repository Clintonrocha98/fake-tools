<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\PermissionsSeeder;
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
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['binance', 'starkbank'] as $channel) {
            config()->set('logging.channels.'.$channel, [
                'driver' => 'monolog',
                'handler' => TestHandler::class,
            ]);
        }
    }
}
