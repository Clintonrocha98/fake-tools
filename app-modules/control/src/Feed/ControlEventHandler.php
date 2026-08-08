<?php

declare(strict_types=1);

namespace He4rt\Control\Feed;

use He4rt\Control\Feed\DTOs\ControlEventContext;
use He4rt\Control\Feed\Models\ControlEvent;
use Illuminate\Support\Facades\Context;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Persiste cada linha de um canal de fake em `control_events`, que é o produtor
 * do feed. A fonte é o que o canal JÁ diz: nenhuma Action é re-instrumentada.
 *
 * Duas invariantes que uma reescrita "óbvia" quebra:
 *
 * 1. **A escrita nunca derruba quem logou.** O fake existe para o consumidor
 *    rodar; um `control_events` indisponível degrada o feed, não o fluxo de
 *    negócio.
 * 2. **A escrita fica FORA da transação de negócio.** Quase toda Action roda
 *    dentro de `DB::transaction()`; um insert na conexão corrente participaria
 *    dela, e o feed perderia justamente os caminhos que dão rollback — os erros
 *    que o dev está depurando — enquanto o arquivo diário continuaria com a
 *    linha. Pior, o cursor furaria: um job pós-response commita `id=101`
 *    enquanto a request ainda segura o `id=100`, e um poll nesse instante
 *    enterra o 100 para sempre. Quem garante o autocommit é a conexão dedicada
 *    de {@see ControlEvent::getConnectionName()}.
 */
final class ControlEventHandler extends AbstractProcessingHandler
{
    public function __construct(private readonly string $channel, Level|int|string $level = Level::Debug)
    {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        try {
            ControlEvent::query()->create([
                'channel' => $this->channel,
                'level' => mb_strtolower($record->level->getName()),
                'message' => $record->message,
                'context' => ControlEventContext::fromArray($record->context),
                'request_id' => $this->requestId(),
                // O instante do record, nunca `now()`: o feed existe para
                // mostrar quando a coisa aconteceu, não quando foi gravada.
                'occurred_at' => $record->datetime,
            ]);
        } catch (Throwable) {
            // Silêncio deliberado: relançar aqui faria uma tabela indisponível
            // derrubar o request de negócio que só queria logar uma linha.
        }
    }

    /**
     * Do Context, não do record: quem popula é o `LogsRequestLifecycle` dos dois
     * fakes, e é assim que os jobs pós-response de uma request saem amarrados
     * a ela.
     */
    private function requestId(): ?string
    {
        $requestId = Context::get('request_id');

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }
}
