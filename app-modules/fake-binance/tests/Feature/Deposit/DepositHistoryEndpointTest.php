<?php

declare(strict_types=1);

use He4rt\FakeBinance\Deposit\Actions\AnnounceCryptoDeposit;
use He4rt\FakeBinance\Deposit\Enums\DepositStatus;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Tests\Support\SignsRequests;
use Illuminate\Support\Facades\Date;

uses(SignsRequests::class);

beforeEach(function (): void {
    $this->configureFakeBinanceCredentials();
    config(['fake-binance-deposit.advance_seconds' => 60]);
    Date::setTestNow(Date::now());
});

it('lists an announced deposit as Pending with no ledger movement', function (): void {
    resolve(AnnounceCryptoDeposit::class)->handle('USDC', 'SOL', '100');

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/hisrec', ['coin' => 'USDC']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJsonCount(1)->assertJson([[
        'coin' => 'USDC',
        'network' => 'SOL',
        'amount' => '100',
        'status' => DepositStatus::Pending->value,
        'completeTime' => null,
    ]]);

    expect($response->json('0.txId'))->toBeString()->not->toBeEmpty()
        ->and($response->json('0.insertTime'))->toBeInt()
        ->and(LedgerAccount::query()->where('asset', 'USDC')->exists())->toBeFalse();
});

it('advances to Credited after one interval, crediting the ledger exactly once across reads', function (): void {
    resolve(AnnounceCryptoDeposit::class)->handle('USDC', 'SOL', '100');

    Date::setTestNow(Date::now()->addSeconds(61));

    $firstRead = $this->getJson($this->signedUri('/sapi/v1/capital/deposit/hisrec', ['coin' => 'USDC']), $this->apiKeyHeader());
    $secondRead = $this->getJson($this->signedUri('/sapi/v1/capital/deposit/hisrec', ['coin' => 'USDC']), $this->apiKeyHeader());

    $firstRead->assertOk()->assertJson([['status' => DepositStatus::Credited->value]]);
    $secondRead->assertOk()->assertJson([['status' => DepositStatus::Credited->value]]);

    expect($firstRead->json('0.completeTime'))->toBeInt();

    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($usdc->free)->toBe('100.000000000000000000');
});

it('jumps straight to Success after two intervals, still crediting exactly once', function (): void {
    resolve(AnnounceCryptoDeposit::class)->handle('USDC', 'SOL', '100');

    Date::setTestNow(Date::now()->addSeconds(121));

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/deposit/hisrec', ['coin' => 'USDC']), $this->apiKeyHeader());

    $response->assertOk()->assertJson([['status' => DepositStatus::Success->value]]);

    $usdc = LedgerAccount::query()->where('asset', 'USDC')->firstOrFail();

    expect($usdc->free)->toBe('100.000000000000000000');

    $persisted = CryptoDeposit::query()->firstOrFail();
    expect($persisted->status)->toBe(DepositStatus::Success)
        ->and($persisted->credited_at)->not->toBeNull();
});

it('honors the status filter over the status the wire reports after the lazy advance', function (): void {
    resolve(AnnounceCryptoDeposit::class)->handle('USDC', 'SOL', '100');
    resolve(AnnounceCryptoDeposit::class)->handle('USDT', 'ETH', '50');

    Date::setTestNow(Date::now()->addSeconds(61));

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/hisrec', ['status' => (string) DepositStatus::Credited->value]),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJsonCount(2);
});

it('filters by coin and network', function (): void {
    resolve(AnnounceCryptoDeposit::class)->handle('USDC', 'SOL', '100');
    resolve(AnnounceCryptoDeposit::class)->handle('USDT', 'ETH', '50');

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/hisrec', ['coin' => 'USDT', 'network' => 'ETH']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJsonCount(1)->assertJson([['coin' => 'USDT', 'network' => 'ETH']]);
});

it('never advances a manual state (WaitingUserConfirm), no matter the age', function (): void {
    CryptoDeposit::factory()->create([
        'status' => DepositStatus::WaitingUserConfirm,
        'announced_at' => Date::now()->subSeconds(1_000),
    ]);

    $response = $this->getJson($this->signedUri('/sapi/v1/capital/deposit/hisrec', []), $this->apiKeyHeader());

    $response->assertOk()->assertJson([['status' => DepositStatus::WaitingUserConfirm->value]]);
    expect(LedgerAccount::query()->exists())->toBeFalse();
});

it('returns an empty array when nothing matches', function (): void {
    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/deposit/hisrec', ['coin' => 'BTC']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertExactJson([]);
});

it('refuses an unsigned request with the spot/wallet error envelope', function (): void {
    $response = $this->getJson('/sapi/v1/capital/deposit/hisrec');

    $response->assertStatus(401)->assertJson(['code' => -2_014]);
});
