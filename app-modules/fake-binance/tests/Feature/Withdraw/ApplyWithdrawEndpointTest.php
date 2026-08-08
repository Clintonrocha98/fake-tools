<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Tests\Support\SignsRequests;
use He4rt\FakeBinance\Withdraw\Enums\WithdrawStatus;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
    config(['fake-binance-withdraw.fees' => ['SOL' => '0.004']]);
});

it('applies a withdraw and returns only the id, debiting exactly amount from the ledger', function (): void {
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

    $response->assertExactJson(['id' => str_replace('-', '', $withdrawal->id)]);
    expect($withdrawal->status)->toBe(WithdrawStatus::AwaitingApproval);
});

it('answers the apply id in the venue format: 32 hex chars, never a hyphenated UUID', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/capital/withdraw/apply', [
            'coin' => 'USDC',
            'address' => 'SomeSolanaAddress',
            'amount' => '8.91',
            'network' => 'SOL',
            'withdrawOrderId' => 'payout-endpoint-id-format',
        ]),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    expect($response->json('id'))->toMatch('/^[0-9a-f]{32}$/');
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

it('accepts an apply without network, withdrawing on the coin default and reporting it in the history', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/capital/withdraw/apply', [
            'coin' => 'USDC',
            'address' => 'SomeSolanaAddress',
            'amount' => '8.91',
            'withdrawOrderId' => 'payout-endpoint-no-network',
        ]),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $this->getJson(
        $this->signedUri('/sapi/v1/capital/withdraw/history', ['withdrawOrderId' => 'payout-endpoint-no-network']),
        $this->apiKeyHeader(),
    )->assertOk()->assertJson([['network' => 'SOL']]);
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
