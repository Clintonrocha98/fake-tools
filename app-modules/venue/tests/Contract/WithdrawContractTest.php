<?php

declare(strict_types=1);

use He4rt\Venue\Ledger\Actions\CreditLedgerAccount;
use He4rt\Venue\Tests\Contract\Support\AssertsRecordedShape;
use He4rt\Venue\Tests\Support\SignsRequests;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;
use Illuminate\Support\Facades\Date;

/*
 * Perna Withdraw: POST /sapi/v1/capital/withdraw/apply e GET
 * /sapi/v1/capital/withdraw/history, batidos como `ApplyWithdrawRequest`/
 * `GetWithdrawHistoryRequest` batem. Nenhum MockClient literal existe hoje no
 * consumidor para estes dois endpoints (só `FxFlowTest.php` exercita
 * `ApplyWithdrawRequest`, com o shape mínimo `{id}`) — o restante do contrato vem
 * direto de `Http/Responses/WithdrawResponse.php` e `WithdrawHistoryResponse.php`,
 * os DTOs que documentam exatamente quais keys o consumidor lê (ver
 * `fixtures/README.md`).
 */

uses(SignsRequests::class, AssertsRecordedShape::class);

beforeEach(function (): void {
    $this->configureVenueCredentials();
    config(['venue-withdraw.fees' => ['SOL' => '0.004']]);
});

it('answers POST /sapi/v1/capital/withdraw/apply with the shape WithdrawResponse reads, signed as ApplyWithdrawRequest signs', function (): void {
    (new CreditLedgerAccount)->handle('USDC', '100');

    $response = $this->postJson(
        $this->signedUri('/sapi/v1/capital/withdraw/apply', [
            'coin' => 'USDC',
            'address' => 'SomeSolanaAddress',
            'amount' => '8.91',
            'network' => 'SOL',
            'withdrawOrderId' => 'contract-apply-1',
        ]),
        [],
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $this->assertMatchesRecordedShape(
        $this->loadContractFixture('withdraw/apply_success.json'),
        $response->json(),
    );
});

it('answers GET /sapi/v1/capital/withdraw/history with the row shape WithdrawHistoryResponse reads, signed as GetWithdrawHistoryRequest signs', function (): void {
    Withdrawal::factory()->create([
        'coin' => 'USDC',
        'network' => 'SOL',
        'address' => 'SomeAddress',
        'amount' => '8.91',
        'transaction_fee' => '0.004',
        'withdraw_order_id' => 'contract-history-1',
        'status' => WithdrawStatus::Completed,
        'tx_id' => '0xdeadbeef',
        'applied_at' => Date::parse('2019-10-12 11:12:02', 'UTC'),
    ]);

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/withdraw/history', ['coin' => 'USDC', 'withdrawOrderId' => 'contract-history-1']),
        $this->apiKeyHeader(),
    );

    $response->assertOk();

    $rows = $response->json();

    expect($rows)->toBeArray()->toHaveCount(1);

    $this->assertMatchesRecordedShape(
        ['result' => $this->loadContractFixture('withdraw/history_row.json')],
        ['result' => $rows[0]],
    );
});

it("never emits a status int outside the 0-6 range WithdrawHistoryResponse's PHPDoc documents", function (): void {
    $wireValues = array_map(fn (WithdrawStatus $case): int => $case->value, WithdrawStatus::cases());

    expect($wireValues)->toEqualCanonicalizing([0, 1, 2, 3, 4, 5, 6]);
});

it('covers all 7 documented withdraw status ints (0-6) over the wire, verbatim', function (WithdrawStatus $status, int $wire): void {
    Withdrawal::factory()->create([
        'coin' => 'USDC',
        'withdraw_order_id' => 'contract-status-'.$wire,
        'status' => $status,
    ]);

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/withdraw/history', ['coin' => 'USDC', 'withdrawOrderId' => 'contract-status-'.$wire]),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([['status' => $wire]]);
})->with([
    'Email Sent (0)' => [WithdrawStatus::EmailSent, 0],
    'Cancelled (1)' => [WithdrawStatus::Cancelled, 1],
    'Awaiting Approval (2)' => [WithdrawStatus::AwaitingApproval, 2],
    'Rejected (3)' => [WithdrawStatus::Rejected, 3],
    'Processing (4)' => [WithdrawStatus::Processing, 4],
    'Failure (5)' => [WithdrawStatus::Failure, 5],
    'Completed (6)' => [WithdrawStatus::Completed, 6],
]);
