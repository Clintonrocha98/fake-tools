<?php

declare(strict_types=1);

use He4rt\FakeBinance\Fiat\Enums\FiatOrderStatus;
use He4rt\FakeBinance\Fiat\Models\FiatWithdrawal;
use He4rt\FakeBinance\Ledger\Actions\CreditLedgerAccount;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Tests\Support\SignsRequests;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
});

/**
 * @return array<string, mixed>
 */
function fiatWithdrawBody(string $clientOrderId = 'fiat-withdraw-1', string $amount = '100'): array
{
    return [
        'currency' => 'BRL',
        'apiPaymentMethod' => 'bank_transfer',
        'amount' => $amount,
        'accountInfo' => [
            'accountNumber' => '12345678',
            'agency' => '0001',
            'bankCodeForPix' => '260',
            'accountType' => 'current',
        ],
        'clientOrderId' => $clientOrderId,
    ];
}

it('accepts the withdraw, debits the whole amount from the BRL ledger and answers the fiat envelope', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '1000');

    $response = $this->postJson(
        $this->signedUri('/sapi/v2/fiat/withdraw', []),
        fiatWithdrawBody(),
        $this->apiKeyHeader(),
    );

    $withdrawal = FiatWithdrawal::query()->firstOrFail();

    $response->assertOk()->assertExactJson([
        'code' => '000000',
        'message' => 'success',
        'data' => ['orderId' => $withdrawal->order_id],
    ]);

    expect($withdrawal->status)->toBe(FiatOrderStatus::Processing)
        ->and($withdrawal->amount)->toBe('100.000000000000000000')
        ->and($withdrawal->client_order_id)->toBe('fiat-withdraw-1');

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($brl->free)->toBe('900.000000000000000000');
});

it('answers a purely numeric orderId, the format the venue serves — never a hyphenated UUID', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '1000');

    $response = $this->postJson($this->signedUri('/sapi/v2/fiat/withdraw', []), fiatWithdrawBody(), $this->apiKeyHeader());

    $response->assertOk();

    expect($response->json('data.orderId'))->toMatch('/^\d{16}$/');
});

it('is idempotent by clientOrderId: repeating returns the same orderId without debiting again', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '1000');

    $first = $this->postJson($this->signedUri('/sapi/v2/fiat/withdraw', []), fiatWithdrawBody(), $this->apiKeyHeader());
    $second = $this->postJson($this->signedUri('/sapi/v2/fiat/withdraw', []), fiatWithdrawBody(), $this->apiKeyHeader());

    $first->assertOk();
    $second->assertOk()->assertExactJson($first->json());

    expect(FiatWithdrawal::query()->count())->toBe(1);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($brl->free)->toBe('900.000000000000000000');
});

it('refuses an insufficient BRL balance in the fiat envelope — HTTP 200, code != 000000, nothing created', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '50');

    $response = $this->postJson(
        $this->signedUri('/sapi/v2/fiat/withdraw', []),
        fiatWithdrawBody(amount: '100'),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([
        'code' => '-16006',
        'success' => false,
    ]);

    expect(FiatWithdrawal::query()->count())->toBe(0);

    $brl = LedgerAccount::query()->where('asset', 'BRL')->firstOrFail();

    expect($brl->free)->toBe('50.000000000000000000');
});

it('refuses an unsupported currency or method with -16010 in the fiat envelope', function (): void {
    (new CreditLedgerAccount)->handle('BRL', '1000');

    $body = fiatWithdrawBody();
    $body['currency'] = 'USD';

    $response = $this->postJson($this->signedUri('/sapi/v2/fiat/withdraw', []), $body, $this->apiKeyHeader());

    $response->assertOk()->assertJson(['code' => '-16010', 'success' => false]);
    expect(FiatWithdrawal::query()->count())->toBe(0);
});

it('refuses a body missing mandatory fields with -1102 in the fiat envelope', function (): void {
    $body = fiatWithdrawBody();
    unset($body['clientOrderId']);

    $response = $this->postJson($this->signedUri('/sapi/v2/fiat/withdraw', []), $body, $this->apiKeyHeader());

    $response->assertStatus(400)->assertJson(['code' => '-1102', 'success' => false]);
});

it('refuses an unsigned request with the fiat error envelope', function (): void {
    $response = $this->postJson('/sapi/v2/fiat/withdraw', fiatWithdrawBody());

    $response->assertStatus(401)->assertExactJson([
        'code' => '-2014',
        'message' => 'API-key format invalid.',
        'success' => false,
        'data' => null,
    ]);
});
