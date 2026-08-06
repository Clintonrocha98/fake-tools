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
use He4rt\FakeBinance\Scenarios\Actions\ArmScenario;
use He4rt\FakeBinance\Scenarios\Actions\DisarmScenario;
use He4rt\FakeBinance\Scenarios\Actions\GetArmedScenario;
use He4rt\FakeBinance\Scenarios\Contracts\LegOutcomeContract;
use He4rt\FakeBinance\Scenarios\DTOs\ArmedScenarioPayload;
use He4rt\FakeBinance\Scenarios\Enums\VenueLeg;
use He4rt\FakeBinance\Scenarios\Models\ArmedScenario;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * O cenário do PRÓXIMO pedido de cada perna. Um switch por desfecho: ligar arma,
 * desligar desarma, e ligar outro desfecho da mesma perna desliga o anterior —
 * dois desfechos para o mesmo pedido se contradizem. Sem clique, nada aqui
 * afeta o happy path.
 *
 * @property-read Schema $form
 */
class ArmedScenariosPage extends Page
{
    /** @var array<string, mixed> */
    public array $data = [];
    protected string $view = 'panel-admin::filament.pages.armed-scenarios';

    protected static ?string $slug = 'armed-scenarios';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBoltSlash;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::FakeBinance;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('panel-admin::fake-binance.armed_scenarios.title');
    }

    public function mount(): void
    {
        $this->form->fill($this->currentState());
    }

    public function getTitle(): string
    {
        return __('panel-admin::fake-binance.armed_scenarios.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(array_map(
                fn (VenueLeg $leg): Section => $this->legSection($leg),
                VenueLeg::cases(),
            ))
            ->statePath('data');
    }

    public function armedOutcomeFor(VenueLeg $leg): ?LegOutcomeContract
    {
        $armed = resolve(GetArmedScenario::class)->handle($leg);

        return $armed instanceof ArmedScenario ? $armed->resolvedOutcome() : null;
    }

    private function legSection(VenueLeg $leg): Section
    {
        $components = [
            TextInput::make($leg->value.'.fraction')
                ->label(__('panel-admin::fake-binance.armed_scenarios.fraction'))
                ->helperText(__('panel-admin::fake-binance.armed_scenarios.fraction_helper'))
                ->numeric(),
            TextInput::make($leg->value.'.errorCode')
                ->label(__('panel-admin::fake-binance.armed_scenarios.error_code'))
                ->numeric(),
            TextInput::make($leg->value.'.rawStatus')
                ->label(__('panel-admin::fake-binance.armed_scenarios.raw_status')),
        ];

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

    private function toggleOutcome(LegOutcomeContract $outcome, bool $enabled): void
    {
        $leg = $outcome->leg();

        if (!$enabled) {
            resolve(DisarmScenario::class)->handle($leg);
            $this->notifyState($outcome, armed: false);

            return;
        }

        resolve(ArmScenario::class)->handle($outcome, $this->payloadFor($leg));

        // Exclusão dentro da perna: o banco já só guarda um, a UI acompanha.
        foreach ($leg->outcomes() as $sibling) {
            if ($sibling !== $outcome) {
                $this->data[$leg->value][$sibling->value] = false;
            }
        }

        $this->notifyState($outcome, armed: true);
    }

    private function payloadFor(VenueLeg $leg): ArmedScenarioPayload
    {
        /** @var array<string, mixed> $state */
        $state = $this->data[$leg->value] ?? [];

        return ArmedScenarioPayload::fromArray([
            'fraction' => $state['fraction'] ?? null,
            'errorCode' => $state['errorCode'] ?? null,
            'rawStatus' => $state['rawStatus'] ?? null,
        ]);
    }

    private function notifyState(LegOutcomeContract $outcome, bool $armed): void
    {
        // getLabel() vem do contrato Filament HasLabel (string|Htmlable|null); todo
        // desfecho concreto devolve string, mas o contrato não garante isso aqui.
        $label = $outcome->getLabel();

        Notification::make()
            ->title(__('panel-admin::fake-binance.armed_scenarios.'.($armed ? 'armed_notification' : 'disarmed_notification'), [
                'outcome' => $label instanceof Htmlable ? $label->toHtml() : ($label ?? ''),
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

        foreach (VenueLeg::cases() as $leg) {
            $armed = resolve(GetArmedScenario::class)->handle($leg);
            $legState = [
                'fraction' => $armed?->payload->fraction,
                'errorCode' => $armed?->payload->errorCode !== null ? (string) $armed->payload->errorCode : null,
                'rawStatus' => $armed?->payload->rawStatus,
            ];

            foreach ($leg->outcomes() as $outcome) {
                $legState[$outcome->value] = $armed instanceof ArmedScenario && $armed->outcome === $outcome->value;
            }

            $state[$leg->value] = $legState;
        }

        return $state;
    }
}
