<?php

declare(strict_types=1);

namespace He4rt\FakeBinance\Spot\Actions;

use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use RuntimeException;

/**
 * GET /api/v3/depth: um livro sintético e previsível em torno do bid/ask do
 * símbolo (ADR-0002 — nunca simula volatilidade). O nível 1 é o próprio
 * bid/ask do {@see GetBookTicker}; cada nível seguinte se afasta
 * `fake-binance-spot.depth.step` do anterior, sempre com a quantidade do
 * símbolo. O `limit` do request apara os níveis servidos, nunca amplia além de
 * `fake-binance-spot.depth.levels`. O shape `{lastUpdateId, bids[[p,q]],
 * asks[[p,q]]}` é o que o `OrderBookDepthResponse` do monolito consumidor lê —
 * níveis por índice numérico, nunca por chave nomeada.
 */
final readonly class GetOrderBookDepth
{
    /**
     * O livro é estático por config, então o id de atualização também é — um
     * valor fixo diferente de zero, nunca um relógio disfarçado de sequência.
     */
    private const int LAST_UPDATE_ID = 1;

    public function __construct(private GetBookTicker $bookTicker) {}

    /**
     * @return array{lastUpdateId: int, bids: list<array{string, string}>, asks: list<array{string, string}>}
     */
    public function handle(SpotSymbol $symbol, ?int $limit = null): array
    {
        $book = $this->bookTicker->handle($symbol);

        $configuredLevels = config()->integer('fake-binance-spot.depth.levels');
        $step = config()->string('fake-binance-spot.depth.step');

        throw_unless(is_numeric($step), RuntimeException::class, 'fake-binance-spot.depth.step must be numeric.');

        $levels = max(1, $limit === null ? $configuredLevels : min($limit, $configuredLevels));

        $bids = [];
        $asks = [];

        for ($level = 0; $level < $levels; $level++) {
            $offset = bcmul((string) $level, $step, 8);

            $bids[] = [LedgerAmount::wire(bcsub($book->bidPrice, $offset, 8)), LedgerAmount::wire($book->bidQty)];
            $asks[] = [LedgerAmount::wire(bcadd($book->askPrice, $offset, 8)), LedgerAmount::wire($book->askQty)];
        }

        return [
            'lastUpdateId' => self::LAST_UPDATE_ID,
            'bids' => $bids,
            'asks' => $asks,
        ];
    }
}
