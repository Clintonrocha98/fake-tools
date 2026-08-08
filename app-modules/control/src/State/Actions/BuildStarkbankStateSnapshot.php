<?php

declare(strict_types=1);

namespace He4rt\Control\State\Actions;

use He4rt\Control\State\DTOs\ArmedScenarioView;
use He4rt\Control\State\DTOs\FakeStateSnapshot;
use He4rt\Control\State\DTOs\ResourceGroupView;
use He4rt\Control\State\DTOs\SwitchboardView;
use He4rt\Control\State\DTOs\SwitchView;
use He4rt\Control\State\Support\ResourceRows;
use He4rt\FakeStarkbank\Brcode\Models\BrcodePayment;
use He4rt\FakeStarkbank\Dict\Models\DictEntry;
use He4rt\FakeStarkbank\Invoice\Models\Invoice;
use He4rt\FakeStarkbank\Scenarios\Actions\GetScenarioSwitchboard;
use He4rt\FakeStarkbank\Scenarios\Enums\PixScenarioSwitch;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Models\ScenarioSwitchboard;
use He4rt\FakeStarkbank\Transfer\Models\Transfer;
use He4rt\FakeStarkbank\Webhook\Models\WebhookEmission;

/**
 * O retrato da malha PIX. Lê os Models DIRETO: `GetInvoice` e `ListInvoices`
 * disparam `AdvanceInvoiceStatus`, e um snapshot que passasse por elas viraria
 * uma máquina de avançar o tempo do fake — a sidebar consulta em loop.
 *
 * Nem o switchboard é lido pela Action: `GetScenarioSwitchboard` CRIA a linha
 * singleton na primeira leitura, e observar não pode escrever.
 */
final readonly class BuildStarkbankStateSnapshot
{
    public function handle(int $limite): FakeStateSnapshot
    {
        return new FakeStateSnapshot(
            switchboard: $this->switchboard(),
            armedScenarios: $this->armedScenarios(),
            resources: [
                new ResourceGroupView(
                    type: 'invoices',
                    total: Invoice::query()->count(),
                    rows: array_values(Invoice::query()->latest()->orderByDesc('id')->limit($limite)->get()
                        ->map(ResourceRows::invoice(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'transfers',
                    total: Transfer::query()->count(),
                    rows: array_values(Transfer::query()->latest()->orderByDesc('id')->limit($limite)->get()
                        ->map(ResourceRows::transfer(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'brcodePayments',
                    total: BrcodePayment::query()->count(),
                    rows: array_values(BrcodePayment::query()->latest()->orderByDesc('id')->limit($limite)->get()
                        ->map(ResourceRows::brcodePayment(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'webhookEmissions',
                    total: WebhookEmission::query()->count(),
                    rows: array_values(WebhookEmission::query()->latest()->limit($limite)->get()
                        ->map(ResourceRows::webhookEmission(...))->all()),
                ),
                new ResourceGroupView(
                    type: 'dictEntries',
                    total: DictEntry::query()->count(),
                    rows: array_values(DictEntry::query()->orderBy('pix_key')->limit($limite)->get()
                        ->map(ResourceRows::dictEntry(...))->all()),
                ),
            ],
        );
    }

    private function switchboard(): SwitchboardView
    {
        // `find`, não `GetScenarioSwitchboard`: aquela Action cria a linha
        // singleton na primeira leitura, e observar não pode escrever.
        $switchboard = ScenarioSwitchboard::query()->find(GetScenarioSwitchboard::SINGLETON_ID);

        return new SwitchboardView(
            switches: array_map(static fn (PixScenarioSwitch $switch): SwitchView => new SwitchView(
                switch: $switch->value,
                label: $switch->getLabel(),
                enabled: (bool) ($switchboard->{$switch->column()} ?? false),
            ), PixScenarioSwitch::cases()),
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
