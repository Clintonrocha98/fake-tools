<?php

declare(strict_types=1);

use He4rt\FakeBinance\Http\Errors\BinanceErrorCode;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\SpotConversionOutcome;
use He4rt\FakeBinance\Scenarios\Exceptions\ScenarioRefusedRequestException;
use He4rt\FakeBinance\Spot\Actions\PlaceMarketOrder;
use He4rt\FakeBinance\Spot\DTOs\PlaceMarketOrderData;
use He4rt\FakeBinance\Spot\Enums\OrderSide;
use He4rt\FakeBinance\Spot\Enums\OrderStatus;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

function armedBuyOrder(string $clientOrderId = 'forex-armed-1'): PlaceMarketOrderData
{
    return new PlaceMarketOrderData(
        symbol: 'USDCBRL',
        side: OrderSide::Buy,
        newClientOrderId: $clientOrderId,
        quoteOrderQty: '15',
    );
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

it('leaves the ledger matching the partial fill — nothing credited then reversed', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.5'));

    $order = resolve(PlaceMarketOrder::class)->handle(armedBuyOrder());

    $brl = LedgerAccount::query()->where('asset', 'BRL')->sole();
    $usdc = LedgerAccount::query()->where('asset', 'USDC')->sole();

    // O BRL debitado é exatamente o cummulativeQuoteQty da ordem parcial, e o
    // USDC creditado é a quantidade executada líquida de comissão.
    expect((string) $brl->free)->toBe(bcsub('1000', (string) $order->cummulative_quote_qty, 18))
        ->and((string) $usdc->free)->toBe(bcsub((string) $order->executed_qty, (string) $order->commission, 18));
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
    (new ArmScenario)->handle(SpotConversionOutcome::FillPartialExpired, new ArmedScenarioPayload(fraction: '0.5'));

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '15',
        'newClientOrderId' => 'forex-wire-1',
    ]), [], $this->apiKeyHeader());

    $response->assertOk()
        ->assertJsonPath('status', 'EXPIRED')
        ->assertJsonCount(1, 'fills');

    expect(bccomp((string) $response->json('executedQty'), '0', 18))->toBe(1);
});

it('carries the refusal as the /api/v3 error envelope', function (): void {
    (new ArmScenario)->handle(SpotConversionOutcome::RefuseWithCode, new ArmedScenarioPayload(errorCode: -2_010));

    $response = $this->postJson($this->signedUri('/api/v3/order', [
        'symbol' => 'USDCBRL',
        'side' => 'BUY',
        'type' => 'MARKET',
        'quoteOrderQty' => '15',
        'newClientOrderId' => 'forex-wire-2',
    ]), [], $this->apiKeyHeader());

    $response->assertStatus(BinanceErrorCode::NewOrderRejected->httpStatus())
        ->assertJsonPath('code', -2_010);
});
