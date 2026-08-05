<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureVenueCredentials());

it('returns the documented balances shape backed by the ledger', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');
    (new CreditLedgerAccount)->handle('USDC', '0');

    $response = $this->getJson($this->signedUri('/api/v3/account'), $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'accountType' => 'SPOT',
        'canTrade' => true,
        'canWithdraw' => true,
        'canDeposit' => true,
        'balances' => [
            ['asset' => 'BRL', 'free' => '100000', 'locked' => '0'],
            ['asset' => 'USDC', 'free' => '0', 'locked' => '0'],
        ],
    ]);

    $response->assertJsonStructure([
        'makerCommission', 'takerCommission', 'canTrade', 'canWithdraw', 'canDeposit',
        'updateTime', 'accountType', 'balances',
    ]);
});

it('returns an empty balances list when the ledger has no accounts yet', function (): void {
    $response = $this->getJson($this->signedUri('/api/v3/account'), $this->apiKeyHeader());

    $response->assertOk()->assertJson(['balances' => []]);
});

it('omits zero balances when omitZeroBalances=true is passed', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');
    (new CreditLedgerAccount)->handle('USDC', '0');

    $response = $this->getJson($this->signedUri('/api/v3/account', ['omitZeroBalances' => 'true']), $this->apiKeyHeader());

    $response->assertOk()->assertJson([
        'balances' => [
            ['asset' => 'BRL', 'free' => '100000', 'locked' => '0'],
        ],
    ]);
});

it('keeps zero balances by default when omitZeroBalances is not passed', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '100000');
    (new CreditLedgerAccount)->handle('USDC', '0');

    $response = $this->getJson($this->signedUri('/api/v3/account'), $this->apiKeyHeader());

    $response->assertJsonCount(2, 'balances');
});

it('refuses an unsigned request with the spot/wallet error envelope', function (): void {
    $response = $this->getJson('/api/v3/account');

    $response->assertStatus(401)->assertExactJson([
        'code' => -2_014,
        'msg' => 'API-key format invalid.',
    ]);
});

it('refuses a request signed with the wrong secret with -1022', function (): void {
    $uri = '/api/v3/account?'.http_build_query($this->signedQuery(secret: 'wrong-secret'));

    $response = $this->getJson($uri, $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => -1_022]);
});
