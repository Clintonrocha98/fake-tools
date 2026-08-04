<?php

declare(strict_types=1);

use He4rt\Venue\Tests\Support\SignsRequests;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureVenueCredentials());

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
