<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Tests\Support\SignsRequests;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureFakeBinanceCredentials());

it('advances a pending withdraw to Completed with a txId across two history reads', function (): void {
    // `advance_seconds` recomputado do zero a partir do env, exatamente como o
    // ServiceProvider faz no boot — reproduz o valor string que `env()` devolve,
    // em vez do int injetado diretamente que os demais testes usam.
    putenv('FAKE_BINANCE_WITHDRAW_ADVANCE_SECONDS=1');
    $freshConfig = require base_path('app-modules/fake-binance/config/fake-binance-withdraw.php');
    config(['fake-binance-withdraw.advance_seconds' => $freshConfig['advance_seconds']]);
    config(['fake-binance-withdraw.fees' => ['SOL' => '0.004']]);

    (new CreditLedgerAccount)->handle('USDC', '100');

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
        'completed_at' => Date::parse('2019-10-12 11:14:30', 'UTC'),
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
        'completeTime' => '2019-10-12 11:14:30',
        'transferType' => 0,
        'confirmNo' => 1,
        'walletType' => 0,
        'txKey' => '',
    ]]);
});

it('honors the status filter over the wire status, returning only matching rows', function (): void {
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'f-completed', 'status' => WithdrawStatus::Completed, 'frozen' => true]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'f-rejected', 'status' => WithdrawStatus::Rejected]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'f-failure', 'status' => WithdrawStatus::Failure]);

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'status' => 6,
    ]), $this->apiKeyHeader());

    $response->assertOk()->assertJsonCount(1)->assertJson([['withdrawOrderId' => 'f-completed', 'status' => 6]]);
});

it('honors status combined with limit, returning only the most recent completed row', function (): void {
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'f-old-completed', 'status' => WithdrawStatus::Completed, 'frozen' => true, 'applied_at' => Date::parse('2019-10-12 10:00:00', 'UTC')]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'f-new-completed', 'status' => WithdrawStatus::Completed, 'frozen' => true, 'applied_at' => Date::parse('2019-10-12 12:00:00', 'UTC')]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'f-processing', 'status' => WithdrawStatus::Processing, 'frozen' => true, 'applied_at' => Date::parse('2019-10-12 13:00:00', 'UTC')]);

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'status' => 6,
        'limit' => 1,
    ]), $this->apiKeyHeader());

    $response->assertOk()->assertJsonCount(1)->assertJson([['withdrawOrderId' => 'f-new-completed']]);
});

it('honors the startTime/endTime window over applied_at', function (): void {
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'w-before', 'frozen' => true, 'applied_at' => Date::parse('2019-10-10 00:00:00', 'UTC')]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'w-inside', 'frozen' => true, 'applied_at' => Date::parse('2019-10-12 12:00:00', 'UTC')]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'w-after', 'frozen' => true, 'applied_at' => Date::parse('2019-10-15 00:00:00', 'UTC')]);

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'startTime' => Date::parse('2019-10-11 00:00:00', 'UTC')->getTimestampMs(),
        'endTime' => Date::parse('2019-10-13 00:00:00', 'UTC')->getTimestampMs(),
    ]), $this->apiKeyHeader());

    $response->assertOk()->assertJsonCount(1)->assertJson([['withdrawOrderId' => 'w-inside']]);
});

it('honors offset, skipping the most recent rows', function (): void {
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'o-oldest', 'frozen' => true, 'applied_at' => Date::parse('2019-10-10 00:00:00', 'UTC')]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'o-middle', 'frozen' => true, 'applied_at' => Date::parse('2019-10-11 00:00:00', 'UTC')]);
    Withdrawal::factory()->create(['coin' => 'USDC', 'withdraw_order_id' => 'o-newest', 'frozen' => true, 'applied_at' => Date::parse('2019-10-12 00:00:00', 'UTC')]);

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/withdraw/history', [
        'coin' => 'USDC',
        'offset' => 1,
        'limit' => 1,
    ]), $this->apiKeyHeader());

    $response->assertOk()->assertJsonCount(1)->assertJson([['withdrawOrderId' => 'o-middle']]);
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
