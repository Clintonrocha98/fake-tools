<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Ledger\Actions\SetLedgerBalance;
use He4rt\FakeBinance\Ledger\Exceptions\InsufficientLedgerBalanceException;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Ledger\Support\LedgerAmount;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Exceptions\ScenarioRefusedRequestException;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder;
use He4rt\FakeBinance\Spot\DTOs\PlaceMarketOrderData;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Enums\SpotSymbol;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

function armedBuyOrder(string $clientOrderId = 'forex-armed-1'): PlaceMarketOrderData
{
    return new PlaceMarketOrderData(
        symbol: SpotSymbol::UsdcBrl,
        side: OrderSide::Buy,
        newClientOrderId: $clientOrderId,
        quoteOrderQty: '15',
    );
}

/**
 * Os mesmos parâmetros de {@see armedBuyOrder()}, na forma de query do
 * `POST /api/v3/order` — os testes de wire assinam esta query.
 *
 * @return array<string, string>
 */
function armedBuyOrderQuery(string $clientOrderId): array
{
    return [
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '15',
        'newClientOrderId' => $clientOrderId,
    ];
}

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();

    LedgerAccount::factory()->create(['asset' => 'BRL', 'free' => '1000']);
    LedgerAccount::factory()->create(['asset' => 'USDC', 'free' => '0']);
});

it('fills only the armed fraction and expires, crediting just that fraction', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.5'));

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    $full = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder('forex-armed-full'));

    expect($order->status)->toBe(OrderStatus::Expired)
        ->and((string) $order->executed_qty)->toBe(bcdiv((string) $full->executed_qty, '2', 18))
        ->and(bccomp((string) $order->cummulative_quote_qty, (string) $full->cummulative_quote_qty, 18))->toBe(-1);
});

it('leaves the ledger matching the partial fill — nothing credited then reversed', function (string $fraction): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: $fraction));

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    $brl = LedgerAccount::query()->where('asset', 'BRL')->sole();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->sole();

    // O BRL debitado é exatamente o cummulativeQuoteQty da ordem parcial, e o
    // USDC creditado é a quantidade executada líquida de comissão. O dataset
    // cobre frações que NÃO fecham na precisão da base (`0.25`, `0.3`, `0.7`):
    // é só nelas que um cummulativeQuoteQty fracionado à parte se separa do que
    // o ledger recalcula a partir do executedQty já truncado.
    expect((string) $brl->free)->toBe(bcsub('1000', (string) $order->cummulative_quote_qty, 18))
        ->and((string) $usdc->free)->toBe(bcsub((string) $order->executed_qty, (string) $order->commission, 18));
})->with(['0.5', '0.25', '0.3', '0.7']);

it('leaves the ledger matching the partial fill on the SELL side too', function (): void {
    resolve(SetLedgerBalance::class)->handle('USDC', '10', '0');

    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.25'));

    // `2.93542074` × `0.25` não fecha em 8 casas — a fração trunca, e é aí que
    // um quote fracionado à parte se separaria do que o ledger recalcula.
    $order = resolve(PlaceMarketOrder::class)->handle(new PlaceMarketOrderData(
        symbol: SpotSymbol::UsdcBrl,
        side: OrderSide::Sell,
        newClientOrderId: 'forex-armed-sell',
        quantity: '2.93542074',
    ));

    $brl = LedgerAccount::query()->where('asset', 'BRL')->sole();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->sole();

    expect((string) $usdc->free)->toBe(bcsub('10', (string) $order->executed_qty, 18))
        ->and((string) $brl->free)->toBe(
            bcadd('1000', bcsub((string) $order->cummulative_quote_qty, (string) $order->commission, 18), 18),
        );
});

it('refuses with the armed code without creating an order or touching the ledger', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -2_010));

    expect(fn (): SpotOrder => resolve(PlaceMarketOrder::class)->handle(armedBuyOrder()))
        ->toThrow(ScenarioRefusedRequestException::class);

    expect(SpotOrder::query()->count())->toBe(0)
        ->and((string) LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('1000.000000000000000000');
});

it('responds REJECTED with an empty fill, leaving the ledger alone', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    expect($order->status)->toBe(OrderStatus::Rejected)
        ->and((string) $order->executed_qty)->toBe('0.000000000000000000')
        ->and((string) $order->cummulative_quote_qty)->toBe('0.000000000000000000')
        ->and($order->fill_price)->toBeNull()
        ->and($order->commission_asset)->toBeNull()
        ->and((string) LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('1000.000000000000000000');
});

it('echoes an unknown wire status while filling normally', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::EmitUnknownStatus, new ArmedScenarioPayload(rawStatus: 'BANANA'));

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    expect($order->raw_status_override)->toBe('BANANA')
        ->and($order->status)->toBe(OrderStatus::Filled)
        ->and(bccomp((string) $order->executed_qty, '0', 18))->toBe(1);
});

it('spends the armed scenario even when the order fails after the plan was read', function (): void {
    resolve(SetLedgerBalance::class)->handle('BRL', '1', '0');

    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.5'));

    // O plano é consultado FORA da transação de propósito: `refuse_with_code`
    // precisa consumir E estourar. A assimetria custa este caso — um saldo
    // insuficiente gasta o cenário sem entregar o desvio.
    expect(fn (): SpotOrder => resolve(PlaceMarketOrder::class)->handle(armedBuyOrder()))
        ->toThrow(InsufficientLedgerBalanceException::class);

    expect(ArmedScenario::query()->count())->toBe(0)
        ->and(SpotOrder::query()->count())->toBe(0);
});

it('goes back to the happy path on the very next order', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    resolve(PlaceMarketOrder::class)->handle(armedBuyOrder('forex-armed-first'));
    $second = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder('forex-armed-second'));

    expect($second->status)->toBe(OrderStatus::Filled)
        ->and($second->raw_status_override)->toBeNull();
});

it('is bit-for-bit the old behaviour when nothing is armed', function (): void {
    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    expect($order->status)->toBe(OrderStatus::Filled)
        ->and($order->raw_status_override)->toBeNull()
        ->and($order->commission_asset)->toBe('USDC')
        ->and($order->fill_price)->not->toBeNull();
});

it('carries the partial fill in the POST response itself, with fills', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.25'));

    $response = $this->postJson(
        $this->signedUri('/api/v3/order', armedBuyOrderQuery('forex-wire-1')),
        [],
        $this->apiKeyHeader(),
    );

    $order = SpotOrder::query()->sole();

    $response->assertOk()
        ->assertJsonPath('status', 'EXPIRED')
        ->assertJsonCount(1, 'fills')
        ->assertJsonPath('executedQty', LedgerAmount::wire((string) $order->executed_qty))
        ->assertJsonPath('cummulativeQuoteQty', LedgerAmount::wire((string) $order->cummulative_quote_qty))
        ->assertJsonPath('fills.0.qty', LedgerAmount::wire((string) $order->executed_qty))
        ->assertJsonPath('fills.0.price', LedgerAmount::wire((string) $order->fill_price));

    // A wire tem que fechar em si mesma: é de `fills` que o
    // `Execution::toConversionResult()` do consumidor recalcula o total quote,
    // então `qty * price` do fill precisa dar exatamente o cummulativeQuoteQty.
    expect(bccomp(
        bcmul((string) $response->json('fills.0.qty'), (string) $response->json('fills.0.price'), 18),
        (string) $response->json('cummulativeQuoteQty'),
        18,
    ))->toBe(0);

    // E o parcial precisa ser MENOR que o fill cheio — sem esta comparação uma
    // implementação que preenchesse tudo e só trocasse o status passaria.
    $full = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder('forex-wire-1-full'));

    expect(bccomp((string) $order->executed_qty, (string) $full->executed_qty, 18))->toBe(-1);
});

it('carries the refusal as the /api/v3 error envelope', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -2_010));

    $response = $this->postJson(
        $this->signedUri('/api/v3/order', armedBuyOrderQuery('forex-wire-2')),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertStatus(BinanceErrorCode::NewOrderRejected->httpStatus())
        ->assertJsonPath('code', -2_010);
});

it('carries the REJECTED shape in the POST response, with an empty fills list', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RespondRejected);

    $response = $this->postJson(
        $this->signedUri('/api/v3/order', armedBuyOrderQuery('forex-wire-3')),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertOk()
        ->assertJsonPath('status', 'REJECTED')
        ->assertJsonPath('executedQty', '0')
        ->assertJsonPath('cummulativeQuoteQty', '0')
        ->assertJsonCount(0, 'fills');

    expect((string) LedgerAccount::query()->where('asset', 'BRL')->sole()->free)->toBe('1000.000000000000000000');
});

it('echoes the unknown wire status in the POST response, filling normally', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::EmitUnknownStatus, new ArmedScenarioPayload(rawStatus: 'BANANA'));

    $response = $this->postJson(
        $this->signedUri('/api/v3/order', armedBuyOrderQuery('forex-wire-4')),
        [],
        $this->apiKeyHeader(),
    );

    $order = SpotOrder::query()->sole();

    $response->assertOk()
        ->assertJsonPath('status', 'BANANA')
        ->assertJsonCount(1, 'fills')
        ->assertJsonPath('executedQty', LedgerAmount::wire((string) $order->executed_qty));

    // O que o desfecho existe para provar: a wire emite vocabulário que o
    // `fromWire()` do consumidor tem de recusar, com a ordem FILLED por baixo.
    expect(OrderStatus::tryFrom('BANANA'))->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Filled);
});

it('answers the happy path instead of a 500 when the armed outcome is stale', function (): void {
    ArmedScenario::factory()->create(['outcome' => 'fill_partial_and_dance']);

    $response = $this->postJson(
        $this->signedUri('/api/v3/order', armedBuyOrderQuery('forex-wire-5')),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJsonPath('status', 'FILLED');

    expect(ArmedScenario::query()->count())->toBe(0);
});
