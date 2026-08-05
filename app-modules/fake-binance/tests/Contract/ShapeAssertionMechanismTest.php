<?php

declare(strict_types=1);

use He4rt\FakeBinance\Tests\Contract\Support\AssertsRecordedShape;
use PHPUnit\Framework\ExpectationFailedException;

/*
 * Prova do mecanismo (critério "Pronto quando" do ticket): um desvio proposital
 * na resposta do fake — uma key removida, renomeada ou de tipo trocado — precisa
 * derrubar {@see AssertsRecordedShape::assertMatchesRecordedShape()}, ou a suíte
 * de contrato inteira é teatro. Cada teste aqui simula o desvio na mão (sem tocar
 * `src/`) e prova que o helper de fato falha — nunca passa em silêncio.
 */

uses(AssertsRecordedShape::class);

it('breaks when a documented key is removed from the response', function (): void {
    $recorded = $this->loadContractFixture('spot/book_ticker.json');

    $drifted = $recorded;
    unset($drifted['askPrice']); // desvio proposital: a key que o consumidor lê desaparece

    expect(fn () => $this->assertMatchesRecordedShape($recorded, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('breaks when a documented key is renamed in the response', function (): void {
    $recorded = $this->loadContractFixture('withdraw/history_row.json');

    $drifted = $recorded;
    $drifted['transaction_fee'] = $drifted['transactionFee']; // desvio proposital: renomeada para snake_case
    unset($drifted['transactionFee']);

    expect(fn () => $this->assertMatchesRecordedShape($recorded, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('breaks when a decimal-string field turns into a float', function (): void {
    $recorded = $this->loadContractFixture('spot/place_order_response.json');

    $drifted = $recorded;
    $drifted['executedQty'] = 2.93; // desvio proposital: float em vez da decimal-string documentada

    expect(fn () => $this->assertMatchesRecordedShape($recorded, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('breaks when an int status field turns into a numeric string', function (): void {
    $recorded = $this->loadContractFixture('withdraw/history_row.json');

    $drifted = $recorded;
    $drifted['status'] = (string) $drifted['status']; // desvio proposital: "6" em vez de 6

    expect(fn () => $this->assertMatchesRecordedShape($recorded, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('breaks when a nested filter object loses one of its keys', function (): void {
    $recorded = $this->loadContractFixture('spot/exchange_info.json');

    $drifted = $recorded;
    unset($drifted['symbols'][0]['filters'][0]['stepSize']); // desvio proposital: LOT_SIZE.stepSize some

    expect(fn () => $this->assertMatchesRecordedShape($recorded, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('breaks when a documented list response turns into a keyed object', function (): void {
    $historyRow = $this->loadContractFixture('withdraw/history_row.json');

    $expected = ['withdrawals' => [$historyRow]]; // GetWithdrawHistory documenta um array de linhas
    $actual = ['withdrawals' => $historyRow]; // desvio proposital: um único objeto, sem o array externo

    expect(fn () => $this->assertMatchesRecordedShape($expected, $actual))
        ->toThrow(ExpectationFailedException::class);
});

it('never breaks on extra keys the actual response carries beyond what the fixture documents', function (): void {
    // A Binance real também carrega mais campos do que o consumidor lê — a
    // comparação por forma é deliberadamente um subconjunto, nunca igualdade
    // exata, então uma key nova no fake nunca derruba o contrato sozinha.
    $recorded = $this->loadContractFixture('spot/book_ticker.json');

    $withExtraField = $recorded + ['weightUsed' => 2];

    $this->assertMatchesRecordedShape($recorded, $withExtraField);

    expect(value: true)->toBeTrue(); // chegar até aqui sem exceção é a prova
});

it('never breaks on a genuinely nullable field the fixture marks as null', function (): void {
    $recorded = $this->loadContractFixture('withdraw/history_row.json');

    $withInfoFilled = $recorded;
    $withInfoFilled['info'] = 'insufficient network fee reserve'; // `info` é nullable — preenchido também é válido

    $this->assertMatchesRecordedShape($recorded, $withInfoFilled);

    expect(value: true)->toBeTrue();
});

it('never breaks when filters arrive in a different order, because the consumer looks up by filterType, not position', function (): void {
    $recorded = $this->loadContractFixture('spot/exchange_info.json');

    $reordered = $recorded;
    $reordered['symbols'][0]['filters'] = array_reverse($recorded['symbols'][0]['filters']); // NOTIONAL antes de LOT_SIZE

    $this->assertMatchesRecordedShape($recorded, $reordered);

    expect(value: true)->toBeTrue();
});

it('breaks when a filterType is renamed, even though every key/type under it still matches', function (): void {
    // `ExchangeInfoResponse::filterByType()` busca por 'LOT_SIZE' — um rename
    // silencioso para 'LOTSIZE' precisa derrubar o contrato mesmo comparando
    // por forma, porque a busca do consumidor é pela KEY discriminadora.
    $recorded = $this->loadContractFixture('spot/exchange_info.json');

    $drifted = $recorded;
    $drifted['symbols'][0]['filters'][0]['filterType'] = 'LOTSIZE';

    expect(fn () => $this->assertMatchesRecordedShape($recorded, $drifted))
        ->toThrow(ExpectationFailedException::class);
});

it('breaks when an exactValue()-marked literal changes value, even though the type stays the same', function (): void {
    $recorded = ['code' => $this->exactValue('000000')];
    $drifted = ['code' => '0'];

    expect(fn () => $this->assertMatchesRecordedShape($recorded, $drifted))
        ->toThrow(ExpectationFailedException::class);
});
