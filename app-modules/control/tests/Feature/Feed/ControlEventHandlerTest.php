<?php

declare(strict_types=1);

use He4rt\Control\Feed\Models\ControlEvent;
use He4rt\FakeBinance\Support\BinanceLog;
use He4rt\FakeStarkbank\Support\StarkbankLog;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| O produtor do feed
|--------------------------------------------------------------------------
|
| Cada linha dos canais `binance`/`starkbank` cai em `control_events` sem que
| nenhuma Action seja re-instrumentada — a fonte é o que o canal já diz.
|
*/

it('persiste uma linha do canal binance com canal, nível, mensagem e contexto', function (): void {
    BinanceLog::info('fake-binance.teste: linha de exemplo', ['order_id' => 42, 'asset' => 'BRL']);

    $evento = ControlEvent::query()->sole();

    expect($evento->channel)->toBe('binance')
        ->and($evento->level)->toBe('info')
        ->and($evento->message)->toBe('fake-binance.teste: linha de exemplo')
        ->and($evento->context->integer('order_id'))->toBe(42)
        ->and($evento->context->string('asset'))->toBe('BRL');
});

it('persiste o canal starkbank separado do binance', function (): void {
    StarkbankLog::warning('fake-starkbank.teste: aviso');
    BinanceLog::error('fake-binance.teste: erro');

    expect(ControlEvent::query()->where('channel', 'starkbank')->sole()->level)->toBe('warning')
        ->and(ControlEvent::query()->where('channel', 'binance')->sole()->level)->toBe('error');
});

it('grava o occurred_at do record, não o instante do flush', function (): void {
    $antes = now()->subSecond();

    BinanceLog::info('fake-binance.teste: timing');

    $evento = ControlEvent::query()->sole();

    expect($evento->occurred_at->greaterThan($antes))->toBeTrue()
        ->and($evento->occurred_at->lessThanOrEqualTo(now()->addSecond()))->toBeTrue();
});

it('amarra o evento ao request_id do Context quando ele existe', function (): void {
    $requestId = '3f1a5c0e-9a2b-4c3d-8e5f-1a2b3c4d5e6f';

    Context::add('request_id', $requestId);

    BinanceLog::info('fake-binance.teste: dentro de uma request');

    expect(ControlEvent::query()->sole()->request_id)->toBe($requestId);
});

it('deixa request_id nulo fora de uma request', function (): void {
    StarkbankLog::info('fake-starkbank.teste: comando artisan');

    expect(ControlEvent::query()->sole()->request_id)->toBeNull();
});

it('não deixa uma falha de escrita derrubar quem logou', function (): void {
    // A tabela some debaixo do handler: o fake existe para o consumidor rodar,
    // então um control_events indisponível degrada o feed, não o fluxo.
    DB::statement('DROP TABLE control_events');

    BinanceLog::info('fake-binance.teste: a linha ainda tem que sair no canal');

    expect(fakeLogRecords('binance'))->toHaveCount(1);
});

it('descarta do contexto o que não sobrevive a uma coluna jsonb', function (): void {
    BinanceLog::info('fake-binance.teste: contexto polimórfico', [
        'escalar' => 'ok',
        'lista' => [1, 2, 3],
        'objeto' => new stdClass,
    ]);

    $contexto = ControlEvent::query()->sole()->context;

    expect($contexto->has('escalar'))->toBeTrue()
        ->and($contexto->has('lista'))->toBeTrue()
        ->and($contexto->has('objeto'))->toBeFalse();
});

it('grava a linha pelo canal também quando o log sai de dentro de uma transação', function (): void {
    // Em teste as duas conexões são a mesma (o rollback do teste precisa
    // alcançar o feed); o que este teste guarda é que logar dentro de uma
    // transação de negócio continua produzindo evento.
    DB::transaction(function (): void {
        StarkbankLog::info('fake-starkbank.teste: dentro da transação');
    });

    expect(ControlEvent::query()->count())->toBe(1);
});

it('não escreve no feed quando o canal não é de um fake', function (): void {
    Log::channel('null')->info('linha fora dos canais dublados');

    expect(ControlEvent::query()->count())->toBe(0);
});
