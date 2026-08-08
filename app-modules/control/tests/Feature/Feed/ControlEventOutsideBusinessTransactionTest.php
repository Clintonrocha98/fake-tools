<?php

declare(strict_types=1);

use He4rt\Control\Feed\Models\ControlEvent;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| A conexão dedicada do produtor do feed
|--------------------------------------------------------------------------
|
| Este é o único teste que roda com as DUAS conexões de verdade — o resto da
| suíte aponta `control.connection` para a default, senão o rollback do teste
| não alcançaria o feed. Aqui o que se verifica é justamente o contrário: que o
| feed NÃO participa da transação de negócio.
|
| Um handler que insere na conexão corrente quebra o feed de duas formas: todo
| caminho que dá rollback some (e some justamente o erro que o dev está
| depurando, enquanto o arquivo diário continua com a linha), e o cursor fura
| quando um job pós-response commita um id maior antes de a request soltar o
| menor.
|
*/

const CONEXAO_DEDICADA = 'control_feed_dedicada';

beforeEach(function (): void {
    // Clonada da default EM RUNTIME: sob `pest --parallel` só a conexão default
    // é reapontada para o banco do worker, então copiar a config estática do
    // arquivo faria este teste escrever no banco errado.
    $default = config('database.default');

    config()->set('database.connections.'.CONEXAO_DEDICADA, config('database.connections.'.$default));
    config()->set('control.connection', CONEXAO_DEDICADA);

    DB::purge(CONEXAO_DEDICADA);
});

afterEach(function (): void {
    // O que a segunda conexão escreve fica commitado — o rollback do teste não
    // o alcança, que é exatamente o ponto. A limpeza é explícita.
    DB::connection(CONEXAO_DEDICADA)->table('control_events')->delete();
    DB::purge(CONEXAO_DEDICADA);
});

it('preserva o evento quando a transação de negócio dá rollback', function (): void {
    try {
        DB::transaction(function (): void {
            StarkbankLog::error('fake-starkbank.teste: a Action falhou no meio da transação');

            throw new RuntimeException('a Action recusou');
        });
    } catch (RuntimeException) {
        // O rollback é o cenário sob teste, não uma falha do teste.
    }

    $eventos = DB::connection(CONEXAO_DEDICADA)->table('control_events')->get();

    expect($eventos)->toHaveCount(1)
        ->and($eventos->first()->message)->toBe('fake-starkbank.teste: a Action falhou no meio da transação');
});

it('deixa o evento visível para outra conexão antes de a transação de negócio commitar', function (): void {
    // O furo do cursor: um job pós-response commita id=101 enquanto a request
    // ainda segura id=100. Se o 100 estivesse preso na transação, um poll nesse
    // instante o enterraria para sempre.
    DB::transaction(function (): void {
        StarkbankLog::info('fake-starkbank.teste: logado com a transação ainda aberta');

        $visiveis = DB::connection(CONEXAO_DEDICADA)->table('control_events')->count();

        expect($visiveis)->toBe(1);
    });
});

it('lê pela conexão dedicada também no Model', function (): void {
    StarkbankLog::info('fake-starkbank.teste: leitura pela conexão dedicada');

    expect(ControlEvent::query()->sole()->getConnectionName())->toBe(CONEXAO_DEDICADA);
});
