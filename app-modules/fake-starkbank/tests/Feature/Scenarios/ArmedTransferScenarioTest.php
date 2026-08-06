<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\TransferOutcome;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Tests\Support\SignsRequests;
use He4rt\FakeStarkbank\Transfer\Enums\TransferStatus;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Contracts\EmitsWebhookEvents;
use He4rt\FakeStarkbank\Webhook\NullWebhookEmitter;

uses(SignsRequests::class);

/*
|--------------------------------------------------------------------------
| Perna assíncrona: transfer
|--------------------------------------------------------------------------
|
| O armado é consumido no POST e a transfer NASCE destinada. `Fail` é o único
| caminho até `failed` sem clique de operador; `Hold` a segura em `processing`.
|
*/

beforeEach(function (): void {
    $this->configureFakeStarkbankClient();
    $this->app->bind(EmitsWebhookEvents::class, NullWebhookEmitter::class);
    config(['fake-starkbank-transfer.advance_seconds' => 60]);
});

function sendArmedTransfer(string $externalId): string
{
    /** @var string $id */
    $id = test()->postSigned('/v2/transfer', ['transfers' => [[
        'amount' => 5_000,
        'name' => 'Ada Lovelace',
        'taxId' => '012.345.678-90',
        'bankCode' => '20018183',
        'branchCode' => '*2Nq4Yw7cBw9utBZAgLOg4kKm8xNXJkYkqUzletoeOPM=',
        'accountNumber' => '*PnAoBLxISgcZ4Widbyqv0rvQx/NWaFn5NQXg/LpyFyIC',
        'accountType' => 'checking',
        'externalId' => $externalId,
        'tags' => [$externalId],
    ]]])->assertOk()->json('transfers.0.id');

    return $id;
}

it('faz a próxima transfer nascer destinada a failed com o motivo armado', function (): void {
    new ArmScenario()->handle(TransferOutcome::Fail, new PixScenarioPayload(reason: 'Conta do favorecido encerrada'));

    $id = sendArmedTransfer('payout-armed-1');
    $transfer = Transfer::query()->findOrFail($id);

    expect($transfer->destined_status)->toBe(TransferStatus::Failed)
        ->and($transfer->failure_reason)->toBe('Conta do favorecido encerrada');

    $this->getSigned('/v2/transfer/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'failed');
});

it('usa o motivo default quando o operador arma Fail sem digitar um', function (): void {
    new ArmScenario()->handle(TransferOutcome::Fail);

    $id = sendArmedTransfer('payout-armed-2');

    expect(Transfer::query()->findOrFail($id)->failure_reason)->toBe('Transferência recusada pela rede');
});

it('segura a transfer retida em processing, por mais que o relógio ande', function (): void {
    new ArmScenario()->handle(TransferOutcome::Hold);

    $id = sendArmedTransfer('payout-armed-3');

    expect(Transfer::query()->findOrFail($id)->held)->toBeTrue();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(2));
    $this->getSigned('/v2/transfer/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'processing');

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHour());
    $this->getSigned('/v2/transfer/'.$id, $this->signedHeaders())
        ->assertOk()
        ->assertJsonPath('transfer.status', 'processing');

    CarbonImmutable::setTestNow();
});

it('consome o armado no POST e devolve a transfer seguinte ao neutro', function (): void {
    new ArmScenario()->handle(TransferOutcome::Hold);

    $armada = sendArmedTransfer('payout-armed-4');
    $neutra = sendArmedTransfer('payout-armed-5');

    expect(ArmedScenario::query()->count())->toBe(0)
        ->and(Transfer::query()->findOrFail($armada)->held)->toBeTrue()
        ->and(Transfer::query()->findOrFail($neutra)->held)->toBeFalse()
        ->and(Transfer::query()->findOrFail($neutra)->destined_status)->toBeNull();
});

it('não gasta o armado num POST repetido pelo mesmo externalId', function (): void {
    // O retry idempotente devolve a transfer existente; gastar o cenário nele
    // deixaria o operador sem o desvio no próximo cash-out de verdade.
    sendArmedTransfer('payout-armed-6');

    new ArmScenario()->handle(TransferOutcome::Fail);

    sendArmedTransfer('payout-armed-6');

    expect(ArmedScenario::query()->count())->toBe(1);
});
