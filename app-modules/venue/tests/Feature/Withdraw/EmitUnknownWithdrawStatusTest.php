<?php

declare(strict_types=1);

use He4rt\Venue\Tests\Support\SignsRequests;
use He4rt\Venue\Withdraw\Actions\EmitUnknownWithdrawStatus;
use He4rt\Venue\Withdraw\Enums\WithdrawStatus;
use He4rt\Venue\Withdraw\Models\Withdrawal;

uses(SignsRequests::class);

beforeEach(fn () => $this->configureVenueCredentials());

it('sets an arbitrary status code outside 0-6, without touching the real status column', function (): void {
    $withdrawal = Withdrawal::factory()->create(['status' => WithdrawStatus::AwaitingApproval]);

    $emitted = (new EmitUnknownWithdrawStatus)($withdrawal, 99);

    expect($emitted->raw_status_override)->toBe(99)
        ->and($emitted->status)->toBe(WithdrawStatus::AwaitingApproval);
});

it('echoes the raw override verbatim on the history endpoint', function (): void {
    $withdrawal = Withdrawal::factory()->create(['coin' => 'USDC', 'status' => WithdrawStatus::AwaitingApproval]);
    (new EmitUnknownWithdrawStatus)($withdrawal, 99);

    $response = $this->getJson(
        $this->signedUri('/sapi/v1/capital/withdraw/history', ['coin' => 'USDC']),
        $this->apiKeyHeader(),
    );

    $response->assertOk()->assertJson([['status' => 99]]);
});
