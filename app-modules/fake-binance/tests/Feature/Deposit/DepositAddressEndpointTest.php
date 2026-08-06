<?php

declare(strict_types=1);

use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
    config(['fake-binance-deposit.addresses' => [
        'SOL' => 'FakeSolAddress1111',
        'ETH' => '0xFakeEthAddress',
    ]]);
});

it('returns the fixed configured address for the coin and network', function (): void {
    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/address', ['coin' => 'USDC', 'network' => 'SOL']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson([
        'coin' => 'USDC',
        'address' => 'FakeSolAddress1111',
        'tag' => '',
        'url' => 'https://explorer.fake-binance.local/address/FakeSolAddress1111',
    ]);
});

it('falls back to the first configured network when network is omitted', function (): void {
    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/address', ['coin' => 'USDC']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson(['address' => 'FakeSolAddress1111']);
});

it('refuses an unmapped network with -1102, never inventing an address', function (): void {
    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/address', ['coin' => 'USDC', 'network' => 'BSC']),
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertJson(['code' => -1_102]);
});

it('refuses a missing coin with -1102', function (): void {
    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/address', []),
        $this->apiKeyHeader(),
    );

    $response->assertStatus(400)->assertJson(['code' => -1_102]);
});

it('refuses an unsigned request with the spot/wallet error envelope', function (): void {
    $response = $this->getJson('/sapi/v1/capital/deposit/address?coin=USDC');

    $response->assertStatus(401)->assertJson(['code' => -2_014]);
});
