<?php

declare(strict_types=1);

namespace He4rt\PanelAdmin\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use He4rt\FakeStarkbank\Invoice\DTOs\InvoicePlan;
use He4rt\FakeStarkbank\Scenarios\Actions\ArmScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\DisarmScenario;
use He4rt\FakeStarkbank\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeStarkbank\Scenarios\Contracts\PixLegOutcomeContract;
use He4rt\FakeStarkbank\Scenarios\DTOs\PixScenarioPayload;
use He4rt\FakeStarkbank\Scenarios\Enums\PixLeg;
use He4rt\FakeStarkbank\Scenarios\Models\ArmedScenario;
use UnitEnum;

/**
 * O cenário do PRÓXIMO pedido (ou evento) de cada perna da malha PIX. Um switch
 * por desfecho: ligar arma, desligar desarma, e ligar outro desfecho da mesma
 * perna desliga o anterior — dois desfechos para o mesmo pedido se contradizem.
 * Sem clique, nada aqui afeta o happy path.
 *
 * Página PRÓPRIA, e não uma extensão da do fake-binance: os switchboards são
 * independentes por fake, e misturar as pernas numa tela só apagaria justamente
 * essa separação.
 *
 * @property-read Schema $form
 */
class PixArmedScenariosPage extends Page implements HasKnowledgeBase
{
    /** @var array<string, mixed> */
    public array $data = [];

    protected string $view = 'panel-admin::filament.pages.pix-armed-scenarios';

    protected static ?string $slug = 'pix-armed-scenarios';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeStarkbank;

    protected static ?int $navigationSort = 7;

    /**
     * @return string[]
     */
    public static function getDocumentation(): array
    {
        return [
            'fake-starkbank.pix-armed-scenarios',
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('panel-admin::fake-starkbank.armed_scenarios.title');
    }

    public function mount(): void
    {
        $this->form->fill($this->currentState());
    }

    public function getTitle(): string
    {
        return __('panel-admin::fake-starkbank.armed_scenarios.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                $this->legSection(...),
                PixLeg::cases(),
            ))
            ->statePath('data');
    }

    public function armedOutcomeFor(PixLeg $leg): ?PixLegOutcomeContract
    {
        $armed = resolve(GetArmedScenario::class)->handle($leg);

        return $armed instanceof ArmedScenario ? $armed->resolvedOutcome() : null;
    }

    /**
     * Os campos de parâmetro só aparecem na perna que tem ao menos um desfecho
     * que os usa ({@see PixLegOutcomeContract::payloadFields()}) — um campo de
     * motivo no card do webhook seria ruído que nenhum desfecho lê.
     */
    private function legSection(PixLeg $leg): Section
    {
        $components = [];

        if ($this->legUses($leg, 'reason')) {
            $components[] = TextInput::make($leg->value.'.reason')
                ->label(__('panel-admin::fake-starkbank.armed_scenarios.reason'))
                ->helperText(__('panel-admin::fake-starkbank.armed_scenarios.reason_helper'))
                ->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->rearm($leg));
        }

        if ($this->legUses($leg, 'extraSeconds')) {
            $components[] = TextInput::make($leg->value.'.extraSeconds')
                ->label(__('panel-admin::fake-starkbank.armed_scenarios.extra_seconds'))
                ->helperText(__('panel-admin::fake-starkbank.armed_scenarios.extra_seconds_helper', [
                    'default' => InvoicePlan::DEFAULT_EXTRA_SECONDS,
                ]))
                ->numeric()
                ->minValue(0)
                ->live(onBlur: true)
                ->afterStateUpdated(fn () => $this->rearm($leg));
        }

        foreach ($leg->outcomes() as $outcome) {
            $components[] = Toggle::make($leg->value.'.'.$outcome->value)
                ->label($outcome->getLabel())
                ->helperText($outcome->getDescription())
                ->onColor('danger')
                ->live()
                ->afterStateUpdated(fn (bool $state) => $this->toggleOutcome($outcome, $state));
        }

        return Section::make($leg->getLabel())
            ->description($leg->getDescription())
            ->schema($components);
    }

    private function legUses(PixLeg $leg, string $field): bool
    {
        return array_any($leg->outcomes(), fn (PixLegOutcomeContract $outcome) => in_array($field, $outcome->payloadFields(), strict: true));
    }

    private function toggleOutcome(PixLegOutcomeContract $outcome, bool $enabled): void
    {
        $leg = $outcome->leg();

        if (!$enabled) {
            resolve(DisarmScenario::class)->handle($leg);
            $this->notify($outcome, 'disarmed_notification');

            return;
        }

        resolve(ArmScenario::class)->handle($outcome, $this->payloadFor($leg));

        // Exclusão dentro da perna: o banco já só guarda um, a UI acompanha.
        foreach ($leg->outcomes() as $sibling) {
            if ($sibling !== $outcome) {
                $this->data[$leg->value][$sibling->value] = false;
            }
        }

        $this->notify($outcome, 'armed_notification');
    }

    /**
     * Editar motivo / segundos com a perna já armada re-arma com o valor novo:
     * sem isto a tela mostraria um parâmetro que o banco não tem, e o pedido
     * seguinte sairia com o anterior, sem sinal nenhum. Perna desarmada não
     * vira armada por digitação — só o switch arma.
     */
    private function rearm(PixLeg $leg): void
    {
        $outcome = $this->armedOutcomeFor($leg);

        if (!$outcome instanceof PixLegOutcomeContract) {
            return;
        }

        resolve(ArmScenario::class)->handle($outcome, $this->payloadFor($leg));

        $this->notify($outcome, 'rearmed_notification');
    }

    private function payloadFor(PixLeg $leg): PixScenarioPayload
    {
        /** @var array<string, mixed> $state */
        $state = $this->data[$leg->value] ?? [];

        return PixScenarioPayload::fromArray([
            'reason' => $state['reason'] ?? null,
            'extraSeconds' => $state['extraSeconds'] ?? null,
        ]);
    }

    private function notify(PixLegOutcomeContract $outcome, string $messageKey): void
    {
        Notification::make()
            ->title(__('panel-admin::fake-starkbank.armed_scenarios.'.$messageKey, [
                'outcome' => $outcome->getLabel(),
            ]))
            ->success()
            ->send();
    }

    /**
     * @return array<string, array<array-key, bool|string|null>>
     */
    private function currentState(): array
    {
        $state = [];

        foreach (PixLeg::cases() as $leg) {
            $armed = resolve(GetArmedScenario::class)->handle($leg);
            $legState = [
                'reason' => $armed?->payload->reason,
                'extraSeconds' => $armed?->payload->extraSeconds !== null ? (string) $armed->payload->extraSeconds : null,
            ];

            foreach ($leg->outcomes() as $outcome) {
                $legState[$outcome->value] = $armed instanceof ArmedScenario && $armed->outcome === $outcome->value;
            }

            $state[$leg->value] = $legState;
        }

        return $state;
    }
}
