<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Tests\Support\SignsRequests;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureVenueCredentials();
    config(['venue-withdraw.fees' => ['SOL' => '0.004']]);
});

it('applies a withdraw and returns only the id, debiting amount+fee from the ledger', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/capital/withdraw/apply', [
            'coin' => 'USDC',
            'address' => 'SomeSolanaAddress',
            'amount' => '8.91',
            'network' => 'SOL',
            'withdrawOrderId' => 'payout-endpoint-1',
        ]),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $withdrawal = Withdrawal::query()->where('withdraw_order_id', 'payout-endpoint-1')->firstOrFail();

    $response->assertExactJson(['id' => $withdrawal->id]);
    expect($withdrawal->status)->toBe(WithdrawStatus::AwaitingApproval);
});

it('refuses with -2010 and creates no withdrawal when the ledger balance is insufficient', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '1');

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/capital/withdraw/apply', [
            'coin' => 'USDC',
            'address' => 'SomeSolanaAddress',
            'amount' => '8.91',
            'network' => 'SOL',
            'withdrawOrderId' => 'payout-endpoint-2',
        ]),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertExactJson([
        'code' => -2_010,
        'msg' => 'Account has insufficient balance for requested action.',
    ]);

    expect(Withdrawal::query()->count())->toBe(0);
});

it('is idempotent at the HTTP level: repeating withdrawOrderId returns the same id without debiting again', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $params = [
        'coin' => 'USDC',
        'address' => 'SomeSolanaAddress',
        'amount' => '8.91',
        'network' => 'SOL',
        'withdrawOrderId' => 'payout-endpoint-3',
    ];

    $first = $this->postJson($this->signedUri('/sapi/v1/capital/withdraw/apply', $params), [], $this->apiKeyHeader());
    $second = $this->postJson($this->signedUri('/sapi/v1/capital/withdraw/apply', $params), [], $this->apiKeyHeader());

    $first->assertOk();
    $second->assertOk()->assertExactJson($first->json());

    expect(Withdrawal::query()->count())->toBe(1);
});

it('refuses a mandatory-parameter-missing apply with -1102', function (): void {
    $response = $this->postJson(
        $this->signedUri('/sapi/v1/capital/withdraw/apply', [
            'coin' => 'USDC',
            'amount' => '8.91',
            'network' => 'SOL',
        ]),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertJson(['code' => -1_102]);
});

it('refuses an unsigned apply request with the spot/wallet error envelope', function (): void {
    $response = $this->postJson('/sapi/v1/capital/withdraw/apply', [
        'coin' => 'USDC',
        'address' => 'SomeSolanaAddress',
        'amount' => '8.91',
        'network' => 'SOL',
    ]);

    $response->assertStatus(401)->assertExactJson([
        'code' => -2_014,
        'msg' => 'API-key format invalid.',
    ]);
});
