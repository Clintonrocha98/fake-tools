<?php

declare(strict_types=1);

namespace He4rt\Control\State\Actions;

use He4rt\Control\State\DTOs\ArmedScenarioView;
use He4rt\Control\State\DTOs\FakeStateSnapshot;
use He4rt\Control\State\DTOs\ResourceGroupView;
use He4rt\Control\State\DTOs\SwitchboardView;
use He4rt\Control\State\DTOs\SwitchView;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeBinance\Deposit\Models\CryptoDeposit;
use He4rt\FakeBinance\Fiat\Models\FiatOrder;
use He4rt\FakeBinance\Ledger\Models\LedgerAccount;
use He4rt\FakeBinance\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeBinance\Scenarios\Enums\ScenarioSwitch;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use He4rt\FakeBinance\Scenarios\Models\ScenarioSwitchboard;
use He4rt\FakeBinance\Spot\Models\SpotOrder;
use He4rt\FakeBinance\Withdraw\Models\Withdrawal;

/**
 * O retrato da venue. Lê os Models DIRETO — nunca `GetFiatOrderDetail`, que
 * avança E CREDITA O LEDGER na leitura, nem `GetWithdrawHistory`, que avança
 * cada saque da página. Um snapshot consultado em loop moveria dinheiro só de
 * ser observado.
 *
 * Os saldos vêm de `LedgerAccount` direto, não de `GetAccountSnapshot`: aquela
 * Action é inofensiva hoje, mas depender disso amarraria o retrato a uma
 * garantia que ninguém prometeu manter.
 */
final readonly class BuildBinanceStateSnapshot
{
    public function handle(int $limite): FakeStateSnapshot
    {
        $contas = LedgerAccount::query()->orderBy('asset')->get();

        return new FakeStateSnapshot(
            switchboard: $this->switchboard(),
            armedScenarios: $this->armedScenarios(),
            resources: [
                new ResourceGroupView(
                    type: 'ledger',
                    total: $contas->count(),
                    rows: array_values($contas->map(ResourceRows::ledgerAccount(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'fiatOrders',
                    total: FiatOrder::query()->count(),
                    rows: array_values(FiatOrder::query()->latest()->limit($limite)->get()
                        ->map(ResourceRows::fiatOrder(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'spotOrders',
                    total: SpotOrder::query()->count(),
                    rows: array_values(SpotOrder::query()->latest()->limit($limite)->get()
                        ->map(ResourceRows::spotOrder(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'withdrawals',
                    total: Withdrawal::query()->count(),
                    rows: array_values(Withdrawal::query()->latest()->limit($limite)->get()
                        ->map(ResourceRows::withdrawal(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'cryptoDeposits',
                    total: CryptoDeposit::query()->count(),
                    rows: array_values(CryptoDeposit::query()->latest()->limit($limite)->get()
                        ->map(ResourceRows::cryptoDeposit(...))->all()),
                ),
            ],
        );
    }

    private function switchboard(): SwitchboardView
    {
        $switchboard = ScenarioSwitchboard::query()->find(GetScenarioSwitchboard::SINGLETON_ID);

        return new SwitchboardView(
            switches: array_map(static fn (ScenarioSwitch $switch): SwitchView => new SwitchView(
                switch: $switch->value,
                label: $switch->getLabel(),
                enabled: (bool) ($switchboard->{$switch->column()} ?? false),
            ), ScenarioSwitch::cases()),
            rateLimitRetryAfterSeconds: $switchboard->rate_limit_retry_after_seconds ?? 30,
        );
    }

    /**
     * @return list<ArmedScenarioView>
     */
    private function armedScenarios(): array
    {
        return array_values(ArmedScenario::query()
            ->orderBy('leg')
            ->get()
            ->map(static fn (ArmedScenario $armado): ArmedScenarioView => new ArmedScenarioView(
                leg: $armado->leg->value,
                legLabel: $armado->leg->getLabel(),
                outcome: $armado->outcome,
                outcomeLabel: $armado->resolvedOutcome()?->getLabel(),
                payload: $armado->payload->toArray(),
                armedAt: $armado->armed_at->toIso8601String(),
            ))
            ->all());
    }
}
