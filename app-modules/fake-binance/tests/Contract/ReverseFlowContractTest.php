<?php

declare(strict_types=1);

use He4rt\FakeBinance\Deposit\Actions\AnnounceCryptoDeposit;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

/*
 * Pernas do fluxo inverso: GET /sapi/v1/capital/deposit/address, GET
 * /sapi/v1/capital/deposit/hisrec e POST /sapi/v2/fiat/withdraw, batidos como
 * `GetDepositAddressRequest`/`GetDepositHistoryRequest`/
 * `RequestFiatWithdrawalRequest` batem. Os shapes vêm dos DTOs do consumidor
 * (`DepositAddressResponse`, `DepositHistoryResponse`, `FiatWithdrawalResponse`)
 * e do fixture verbatim de `WriteRoutesMappingTest.php` para o envelope fiat.
 */

uses(SignsRequests::class, AssertsRecordedShape::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
});

it('answers GET /sapi/v1/capital/deposit/address with the shape DepositAddressResponse reads', function (): void {
    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/address', ['coin' => 'USDC', 'network' => 'SOL']),
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('deposit/address.json'),
        $response->json(),
    );
});

it('answers GET /sapi/v1/capital/deposit/hisrec with the row shape DepositHistoryResponse reads', function (): void {
    resolve(AnnounceCryptoDeposit::class)->handle('USDC', 'SOL', '100');

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/hisrec', ['coin' => 'USDC']),
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $rows = $response->json();

    expect($rows)->toBeArray()->toHaveCount(1);

    $this->assertMatchesRecordedShape(
        ['result' => $this->loadContractFixture('deposit/hisrec_row.json')],
        ['result' => $rows[0]],
    );
});

it('answers POST /sapi/v2/fiat/withdraw with the verbatim fiat envelope FiatWithdrawalResponse reads', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '1000');

    $response = $this->postJson(
        $this->signedUri('/sapi/v2/fiat/withdraw', []),
        [
            'currency' => 'BRL',
            'apiPaymentMethod' => 'bank_transfer',
            'amount' => '100',
            'accountInfo' => ['accountNumber' => '12345678'],
            'clientOrderId' => 'contract-fiat-withdraw-1',
        ],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $fixture = $this->loadContractFixture('fiat/withdraw_success.json');
    $fixture['code'] = $this->exactValue('000000');

    $this->assertMatchesRecordedShape($fixture, $response->json());
});
