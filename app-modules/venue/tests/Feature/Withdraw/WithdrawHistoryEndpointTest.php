<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Tests\Support\SignsRequests;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureVenueCredentials());

it('advances a pending withdraw to Completed with a txId across two history reads', function (): void {
    // `advance_seconds` recomputado do zero a partir do env, exatamente como o
    // ServiceProvider faz no boot — reproduz o valor string que `env()` devolve,
    // em vez do int injetado diretamente que os demais testes usam.
    putenv('FAKE_BINANCE_WITHDRAW_ADVANCE_SECONDS=1');
    $freshConfig = require base_path('app-modules/venue/config/venue-withdraw.php');
    config(['venue-withdraw.advance_seconds' => $freshConfig['advance_seconds']]);
    config(['venue-withdraw.fees' => ['SOL' => '0.004']]);

    (new CreditLedgerAccount)('USDC', '100');

    $applyResponse = $this->postJson(
        $this->signedUri('/sapi/v1/capital/withdraw/apply', [
            'coin' => 'USDC',
            'address' => 'SomeSolanaAddress',
            'amount' => '8.91',
            'network' => 'SOL',
            'withdrawOrderId' => 'payout-lazy-advance',
        ]),
        [],
        $this->apiKeyHeader(),
    );
    $applyResponse->assertOk();

    $firstRead = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'withdrawOrderId' => 'payout-lazy-advance',
    ]), $this->apiKeyHeader());
    $firstRead->assertOk();
    expect($firstRead->json('0.status'))->toBe(WithdrawStatus::AwaitingApproval->value)
        ->and($firstRead->json('0.txId'))->toBeNull();

    Date::setTestNow(Date::now()->addSeconds(3));

    $secondRead = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'withdrawOrderId' => 'payout-lazy-advance',
    ]), $this->apiKeyHeader());
    $secondRead->assertOk();
    expect($secondRead->json('0.status'))->toBe(WithdrawStatus::Completed->value)
        ->and($secondRead->json('0.txId'))->not->toBeNull();

    $persisted = Withdrawal::query()->where('withdraw_order_id', 'payout-lazy-advance')->firstOrFail();
    expect($persisted->status)->toBe(WithdrawStatus::Completed)
        ->and($persisted->tx_id)->not->toBeNull();
});

it('returns the documented history array filtered by coin and withdrawOrderId', function (): void {
    Withdrawal::factory()->create([
        'coin' => 'USDC',
        'network' => 'SOL',
        'address' => 'SomeAddress',
        'amount' => '8.91',
        'transaction_fee' => '0.004',
        'withdraw_order_id' => 'payout-history-1',
        'status' => WithdrawStatus::Completed,
        'tx_id' => '0xdeadbeef',
        'applied_at' => Date::parse('2019-10-12 11:12:02', 'UTC'),
    ]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'other-order']);
    Withdrawal::factory()->create(['coin' => 'USDT', 'withdraw_order_id' => 'other-order-usdt']);

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'withdrawOrderId' => 'payout-history-1',
    ]), $this->apiKeyHeader());

    $response->assertOk()->assertExactJson([[
        'id' => Withdrawal::query()->where('withdraw_order_id', 'payout-history-1')->firstOrFail()->id,
        'withdrawOrderId' => 'payout-history-1',
        'coin' => 'USDC',
        'network' => 'SOL',
        'address' => 'SomeAddress',
        'amount' => '8.91',
        'transactionFee' => '0.004',
        'status' => 6,
        'txId' => '0xdeadbeef',
        'info' => null,
        'applyTime' => '2019-10-12 11:12:02',
    ]]);
});

it('returns an empty array when nothing matches', function (): void {
    $response = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'withdrawOrderId' => 'nonexistent',
    ]), $this->apiKeyHeader());

    $response->assertOk()->assertExactJson([]);
});

it('refuses an unsigned history request with the spot/wallet error envelope', function (): void {
    $response = $this->getJson('/sapi/v1/capital/withdraw/history?coin=USDC');

    $response->assertStatus(401)->assertExactJson([
        'code' => -2_014,
        'msg' => 'API-key format invalid.',
    ]);
});
