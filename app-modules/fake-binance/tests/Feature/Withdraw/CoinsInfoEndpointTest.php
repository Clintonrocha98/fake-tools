<?php

declare(strict_types=1);

use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
    config(['fake-binance-withdraw.fees' => ['SOL' => '0.004', 'ETH' => '0.003']]);
});

it('exposes every configured network with the exact fee the withdraw/apply charges', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/config/getall', []), $this->apiKeyHeader());

    $response->assertOk()->assertJson([[
        'coin' => 'USDC',
        'name' => 'USDC',
        'depositAllEnable' => true,
        'withdrawAllEnable' => true,
        'free' => '100',
        'locked' => '0',
        'isLegalMoney' => false,
        'networkList' => [
            ['network' => 'SOL', 'coin' => 'USDC', 'withdrawEnable' => true, 'depositEnable' => true, 'withdrawFee' => '0.004', 'withdrawMin' => '0.004'],
            ['network' => 'ETH', 'coin' => 'USDC', 'withdrawEnable' => true, 'depositEnable' => true, 'withdrawFee' => '0.003', 'withdrawMin' => '0.003'],
        ],
    ]]);
});

it('lists one coin per ledger asset, flagging BRL as legal money', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '1000');
    (new CreditLedgerAccount)->handle('USDC', '100');

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/config/getall', []), $this->apiKeyHeader());

    $response->assertOk()->assertJsonCount(2)->assertJson([
        ['coin' => 'BRL', 'isLegalMoney' => true],
        ['coin' => 'USDC', 'isLegalMoney' => false],
    ]);
});

it('returns an empty list when the ledger has no accounts yet', function (): void {
    $response = $this->getJson($this->signedUri('/sapi/v1/capital/config/getall', []), $this->apiKeyHeader());

    $response->assertOk()->assertExactJson([]);
});

it('refuses an unsigned request with the spot/wallet error envelope', function (): void {
    $response = $this->getJson('/sapi/v1/capital/config/getall');

    $response->assertStatus(401)->assertJson(['code' => -2_014]);
});
